<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assistant\Models\AiActionProposal;
use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiDocumentExtraction;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Services\ActionProposalService;
use App\Domain\Assistant\Services\AiCapabilities;
use App\Domain\Assistant\Services\AssistantOrchestrator;
use App\Domain\Assistant\Services\AssistantUsageService;
use App\Domain\Identity\Services\LegalEvidence;
use App\Domain\Portfolio\Services\PlanService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ProductFeatures;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssistantController extends Controller
{
    public function __construct(
        private readonly AssistantOrchestrator $assistant,
        private readonly AssistantUsageService $usage,
    ) {}

    public function index(Request $request)
    {
        $portfolio = $request->user()->portfolio();

        return [
            'available' => app(ProductFeatures::class)->assistant($request->user()),
            'enabled' => $this->enabled($request),
            'notice_version' => config('assistant.notice_version'),
            'retention_days' => config('assistant.retention_days'),
            'usage' => $this->usage->summary($portfolio, $request->user()),
            'capabilities' => app(AiCapabilities::class)->forUser($portfolio, $request->user()),
            'conversations' => AiConversation::query()
                ->where('portfolio_id', $portfolio->id)->where('user_id', $request->user()->id)
                ->orderByDesc('last_message_at')->orderByDesc('id')->limit(20)->get(),
        ];
    }

    public function store(Request $request)
    {
        abort_unless($this->enabled($request), 403, 'Activa el asistente después de leer la información de privacidad.');

        return $this->withLock($request, function () use ($request) {
            $request->user()->refresh();
            abort_unless($this->enabled($request), 403, 'El asistente está desactivado.');
            abort_if(AiConversation::where('user_id', $request->user()->id)->count() >= config('assistant.max_conversations'), 422, 'Elimina alguna conversación antes de crear otra.');
            $conversation = AiConversation::create([
                'portfolio_id' => $request->user()->portfolio()->id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json($conversation, 201);
        });
    }

    public function show(Request $request, AiConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        return ['conversation' => $conversation, 'messages' => $conversation->messages()->where('created_at', '>=', now()->subDays(config('assistant.retention_days')))->latest('id')->limit(config('assistant.max_messages'))->get()->reverse()->values()];
    }

    public function destroy(Request $request, AiConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        return $this->withLock($request, function () use ($conversation) {
            DB::transaction(function () use ($conversation) {
                AiActionProposal::whereIn('run_id', AiRun::where('conversation_id', $conversation->id)->select('id'))->delete();
                $conversation->delete();
            });

            return response()->noContent();
        });
    }

    public function send(Request $request, AiConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        abort_unless($this->enabled($request), 403, 'Activa el asistente después de leer la información de privacidad.');

        return $this->withLock($request, fn () => $this->sendLocked($request, $conversation));
    }

    private function sendLocked(Request $request, AiConversation $conversation)
    {
        $request->user()->refresh();
        abort_unless($this->enabled($request), 403, 'El asistente está desactivado.');
        $conversation->refresh();
        $data = $request->validate(['message' => ['required', 'string', 'max:2000'], 'property_id' => ['nullable', 'integer', 'min:1'], 'client_request_id' => ['sometimes', 'required', 'uuid']]);
        $portfolio = $request->user()->portfolio();
        if (! empty($data['property_id'])) {
            $portfolio->properties()->findOrFail($data['property_id']);
        }
        $clientId = $data['client_request_id'] ?? (string) Str::uuid();
        $hash = hash_hmac('sha256', json_encode([$conversation->id, trim($data['message']), $data['property_id'] ?? null], JSON_THROW_ON_ERROR), (string) config('app.key'));
        $existing = AiRun::where('user_id', $request->user()->id)->where('client_request_id', $clientId)->first();
        if ($existing) {
            abort_unless($existing->portfolio_id === $portfolio->id && $existing->conversation_id === $conversation->id
                && hash_equals($existing->request_hash, $hash), 409, 'Este identificador ya corresponde a otra consulta.');

            return $this->runResponse($request, $existing);
        }
        $usage = $this->usage->summary($portfolio, $request->user());
        if ($usage['remaining'] < 1) {
            throw ValidationException::withMessages(['message' => ['Has alcanzado el límite mensual del asistente.']]);
        }
        if (! config('assistant.enabled') || ! filled(config('services.openai.key'))) {
            return response()->json(['message' => 'El asistente todavía no está configurado.'], 503);
        }
        abort_if($conversation->messages()->count() >= config('assistant.max_messages'), 422, 'Esta conversación está completa. Inicia otra.');
        [$run, $userMessage] = DB::transaction(function () use ($request, $portfolio, $conversation, $data, $clientId, $hash) {
            $this->usage->reserve($portfolio, $request->user());
            $run = AiRun::create([
                'portfolio_id' => $portfolio->id, 'user_id' => $request->user()->id, 'conversation_id' => $conversation->id,
                'client_request_id' => $clientId, 'request_hash' => $hash,
                'plan' => app(PlanService::class)->effectiveCode($portfolio),
                'routing_version' => config('ai.routing_version'), 'billing_month' => now()->startOfMonth()->toDateString(),
            ]);
            $userMessage = $conversation->messages()->create(['role' => 'user', 'content' => trim($data['message'])]);
            $run->update(['user_message_id' => $userMessage->id]);
            $conversation->update(['title' => $conversation->title ?: 'Consulta del '.today()->format('d/m/Y'), 'last_message_at' => now()]);

            return [$run, $userMessage];
        });

        $history = $conversation->messages()->where('created_at', '>=', now()->subDays(config('assistant.retention_days')))->latest('id')->limit((int) config('assistant.history_messages'))->get()->reverse()->values();
        try {
            $answer = $this->assistant->answer($portfolio, $request->user(), $history, $data['property_id'] ?? null, $run);
            $request->user()->refresh();
            abort_unless($this->enabled($request) && AiConversation::whereKey($conversation->id)->exists(), 403);
            DB::transaction(function () use ($conversation, $answer, $run) {
                $assistantMessage = $conversation->messages()->create([
                    'role' => 'assistant', 'content' => $answer['content'], 'model' => $answer['model'],
                    'input_tokens' => $answer['input_tokens'], 'output_tokens' => $answer['output_tokens'], 'metadata' => $answer['metadata'],
                ]);
                $run->update(['status' => 'completed', 'assistant_message_id' => $assistantMessage->id, 'finished_at' => now()]);
                $conversation->update(['last_message_at' => now()]);
            });
        } catch (\Throwable $exception) {
            Log::warning('Assistant request failed', ['user_id' => $request->user()->id, 'conversation_id' => $conversation->id, 'type' => get_class($exception)]);

            $run->update(['status' => 'failed', 'error_code' => 'request_failed', 'finished_at' => now()]);
        }

        return $this->runResponse($request, $run->fresh());
    }

    public function run(Request $request, AiRun $run)
    {
        abort_unless($run->portfolio_id === $request->user()->portfolio()?->id && $run->user_id === $request->user()->id, 404);
        abort_unless($run->conversation_id && $this->enabled($request), 404);

        return $this->runResponse($request, $run);
    }

    private function runResponse(Request $request, AiRun $run)
    {
        $conversation = AiConversation::whereKey($run->conversation_id)->firstOrFail();
        $messages = $conversation->messages();
        $assistantMessage = (clone $messages)->find($run->assistant_message_id);
        if ($assistantMessage && isset($assistantMessage->metadata['proposals'])) {
            $proposals = AiActionProposal::where('run_id', $run->id)->where('user_id', $request->user()->id)->get();
            $assistantMessage->metadata = [...$assistantMessage->metadata,
                'proposals' => $proposals->map(fn ($proposal) => app(ActionProposalService::class)->present($proposal))->all()];
        }
        // A worker/process that died cannot be restarted with a new side effect by replaying its request.
        if ($run->status === 'processing' && $run->created_at->lt(now()->subMinutes(2))) {
            $run->update(['status' => 'failed', 'error_code' => 'interrupted', 'finished_at' => now(), 'cost_incomplete' => true]);
        }

        $data = [
            'run' => $run->publicStatus(), 'conversation' => $conversation,
            'user_message' => (clone $messages)->find($run->user_message_id),
            'assistant_message' => $assistantMessage,
            'usage' => $this->usage->summary($request->user()->portfolio(), $request->user()),
        ];
        if ($run->status === 'failed') {
            return response()->json([...$data, 'message' => 'Ahora mismo no puedo responder. Inténtalo más tarde y, si el problema persiste, escribe a soporte de Alquivo. La consulta iniciada cuenta para el límite mensual.'], 502);
        }

        return response()->json($data, $run->status === 'processing' ? 202 : 200);
    }

    public function enable(Request $request)
    {
        $request->validate(['notice_version' => ['required', Rule::in([config('assistant.notice_version')])], 'accepted' => ['accepted']]);

        return $this->withLock($request, function () use ($request) {
            DB::transaction(function () use ($request) {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                app(LegalEvidence::class)->accept($user, 'assistant', config('assistant.notice_version'));
                $alreadyAccepted = $user->assistant_enabled_at && $user->assistant_notice_version === config('assistant.notice_version');
                $user->forceFill(['assistant_enabled_at' => $alreadyAccepted ? $user->assistant_enabled_at : now(),
                    'assistant_notice_version' => config('assistant.notice_version')])->save();
            });
            $request->user()->refresh();
            SecurityAudit::record('assistant.enabled', $request->user()->id);

            return ['enabled' => true];
        });
    }

    public function disable(Request $request)
    {
        return $this->withLock($request, function () use ($request) {
            DB::transaction(function () use ($request) {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                app(LegalEvidence::class)->withdraw($user, 'assistant', $user->assistant_notice_version);
                app(LegalEvidence::class)->withdraw($user, 'document_ai', $user->document_ai_notice_version);
                $user->forceFill(['assistant_enabled_at' => null, 'assistant_notice_version' => null,
                    'document_ai_accepted_at' => null, 'document_ai_notice_version' => null])->save();
                AiDocumentExtraction::where('user_id', $user->id)->where('status', '!=', 'confirmed')
                    ->update(['status' => 'cancelled', 'draft' => null]);
                AiDocumentExtraction::where('user_id', $user->id)->where('status', 'confirmed')->update(['draft' => null]);
                AiActionProposal::where('user_id', $request->user()->id)->delete();
                AiConversation::where('user_id', $request->user()->id)->delete();
            });
            $request->user()->refresh();
            SecurityAudit::record('assistant.disabled', $request->user()->id);

            return response()->noContent();
        });
    }

    private function enabled(Request $request): bool
    {
        return $request->user()->assistant_enabled_at !== null
            && $request->user()->assistant_notice_version === config('assistant.notice_version');
    }

    private function withLock(Request $request, callable $callback)
    {
        $lock = Cache::lock('assistant-user:'.$request->user()->id, 90);
        abort_unless($lock->get(), 409, 'Hay una consulta en curso. Espera a que termine.');
        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function authorizeConversation(Request $request, AiConversation $conversation): void
    {
        abort_unless(
            $conversation->portfolio_id === $request->user()->portfolio()?->id
            && $conversation->user_id === $request->user()->id,
            404,
        );
    }
}
