<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Services\AssistantUsageService;
use App\Domain\Assistant\Services\OpenAiPortfolioAssistant;
use App\Http\Controllers\Controller;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssistantController extends Controller
{
    public function __construct(
        private readonly OpenAiPortfolioAssistant $assistant,
        private readonly AssistantUsageService $usage,
    ) {}

    public function index(Request $request)
    {
        $portfolio = $request->user()->portfolio();

        return [
            'available' => app(\App\Support\ProductFeatures::class)->assistant(),
            'enabled' => $this->enabled($request),
            'notice_version' => config('assistant.notice_version'),
            'retention_days' => config('assistant.retention_days'),
            'usage' => $this->usage->summary($portfolio, $request->user()),
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
            $conversation->delete();

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
        $data = $request->validate(['message' => ['required', 'string', 'max:2000'], 'property_id' => ['nullable', 'integer', 'min:1']]);
        $portfolio = $request->user()->portfolio();
        if (! empty($data['property_id'])) {
            $portfolio->properties()->findOrFail($data['property_id']);
        }
        $usage = $this->usage->summary($portfolio, $request->user());
        if ($usage['remaining'] < 1) {
            throw ValidationException::withMessages(['message' => ['Has alcanzado el límite mensual del asistente.']]);
        }
        if (! config('assistant.enabled') || ! filled(config('services.openai.key'))) {
            return response()->json(['message' => 'El asistente todavía no está configurado.'], 503);
        }
        abort_if($conversation->messages()->count() >= config('assistant.max_messages'), 422, 'Esta conversación está completa. Inicia otra.');
        $this->usage->reserve($portfolio, $request->user());

        $userMessage = $conversation->messages()->create(['role' => 'user', 'content' => trim($data['message'])]);
        if (! $conversation->title) {
            $conversation->title = 'Consulta del '.today()->format('d/m/Y');
        }
        $conversation->last_message_at = now();
        $conversation->save();

        $history = $conversation->messages()->where('created_at', '>=', now()->subDays(config('assistant.retention_days')))->latest('id')->limit((int) config('assistant.history_messages'))->get()->reverse()->values();
        try {
            $answer = $this->assistant->answer($portfolio, $request->user(), $history, $data['property_id'] ?? null);
        } catch (\Throwable $exception) {
            Log::warning('Assistant request failed', ['user_id' => $request->user()->id, 'conversation_id' => $conversation->id, 'type' => get_class($exception)]);

            return response()->json(['message' => 'Ahora mismo no puedo responder. Inténtalo más tarde y, si el problema persiste, escribe a soporte de Alquivo. La consulta iniciada cuenta para el límite mensual.', 'user_message' => $userMessage, 'usage' => $this->usage->summary($portfolio, $request->user())], 502);
        }

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant', 'content' => $answer['content'], 'model' => $answer['model'],
            'input_tokens' => $answer['input_tokens'], 'output_tokens' => $answer['output_tokens'],
            'metadata' => $answer['metadata'],
        ]);
        $conversation->update(['last_message_at' => now()]);

        return [
            'conversation' => $conversation->fresh(),
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
            'usage' => $this->usage->summary($portfolio, $request->user()),
        ];
    }

    public function enable(Request $request)
    {
        $request->validate(['notice_version' => ['required', Rule::in([config('assistant.notice_version')])], 'accepted' => ['accepted']]);

        return $this->withLock($request, function () use ($request) {
            $request->user()->forceFill(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')])->save();
            SecurityAudit::record('assistant.enabled', $request->user()->id);

            return ['enabled' => true];
        });
    }

    public function disable(Request $request)
    {
        return $this->withLock($request, function () use ($request) {
            DB::transaction(function () use ($request) {
                $request->user()->forceFill(['assistant_enabled_at' => null, 'assistant_notice_version' => null])->save();
                AiConversation::where('user_id', $request->user()->id)->delete();
            });
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
