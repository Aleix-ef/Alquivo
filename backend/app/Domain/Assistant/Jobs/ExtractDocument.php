<?php

namespace App\Domain\Assistant\Jobs;

use App\Domain\Assistant\Documents\DocumentAiAccess;
use App\Domain\Assistant\Documents\DocumentDemoFixtures;
use App\Domain\Assistant\Documents\DocumentExtractionSchema;
use App\Domain\Assistant\Documents\DocumentFileGuard;
use App\Domain\Assistant\Models\AiDocumentExtraction;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Providers\FakeAIProvider;
use App\Domain\Assistant\Services\AIModelRouter;
use App\Domain\Assistant\Services\AiRunLedger;
use App\Domain\Documents\Models\Document;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PlanService;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ExtractDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 70;

    public bool $failOnTimeout = true;

    // Jobs contain only an opaque ID. No file bytes, prompts or drafts in the queue/failed_jobs.
    public function __construct(public string $extractionId) {}

    public function handle(): void
    {
        $claimed = AiDocumentExtraction::whereKey($this->extractionId)->where('status', 'queued')
            ->update(['status' => 'processing', 'started_at' => now(), 'attempts' => DB::raw('attempts + 1')]);
        if (! $claimed) {
            return; // Redelivery must never trigger another inference.
        }
        $step = null;
        $run = null;
        $started = microtime(true);
        $error = 'file_rejected';
        try {
            $extraction = AiDocumentExtraction::findOrFail($this->extractionId);
            [$user, $portfolio, $document] = $this->context($extraction);
            $file = app(DocumentFileGuard::class)->read($document);
            abort_unless(hash_equals($file['hash'], $extraction->file_hash), 409);
            $run = AiRun::create(['user_id' => $user->id, 'portfolio_id' => $portfolio->id,
                'client_request_id' => (string) Str::uuid(), 'request_hash' => $extraction->file_hash,
                'feature' => 'document_'.$extraction->kind, 'plan' => app(PlanService::class)->effectiveCode($portfolio),
                'routing_version' => config('ai.routing_version'), 'billing_month' => now()->startOfMonth()->toDateString()]);
            $extraction->update(['run_id' => $run->id]);
            $route = app(AIModelRouter::class)->route('document');
            $request = app(DocumentExtractionSchema::class)->request($extraction->kind, $file, $route);
            $error = 'budget_exceeded';
            $step = app(AiRunLedger::class)->reserveDocument($run, $route);
            $step->update(['metadata' => [...$step->metadata, 'simulated' => true]]);
            $error = 'provider_failed';
            // Hard safety boundary: no AIProviderInterface/OpenAIProvider resolution in this release.
            $provider = app()->bound(FakeAIProvider::class) ? app(FakeAIProvider::class)
                : app(DocumentDemoFixtures::class)->provider($extraction->kind, $file['bytes']);
            $response = $provider->generate($request, (float) config('ai_documents.timeout_seconds'));
            app(AiRunLedger::class)->completeCall($step, $response, (int) ((microtime(true) - $started) * 1000), $portfolio, $user);
            $error = 'invalid_extraction';
            $detected = app(DocumentExtractionSchema::class)->parse($extraction->kind, $response, $file['pages']);
            DB::transaction(function () use ($detected, $run) {
                $extraction = AiDocumentExtraction::findOrFail($this->extractionId);
                User::whereKey($extraction->user_id)->lockForUpdate()->firstOrFail();
                Portfolio::whereKey($extraction->portfolio_id)->lockForUpdate()->firstOrFail();
                $extraction = AiDocumentExtraction::whereKey($this->extractionId)->lockForUpdate()->firstOrFail();
                [$user, $portfolio] = $this->context($extraction);
                abort_unless($extraction->status === 'processing', 409);
                $candidates = [];
                if ($detected['property_hint'] && collect($detected['evidence'])->contains('field', 'property_hint')) {
                    $hint = mb_strtolower(trim($detected['property_hint']));
                    $candidates = $portfolio->properties()->select(['id', 'name', 'address_line'])->get()
                        ->filter(fn ($p) => mb_strtolower(trim($p->name)) === $hint || mb_strtolower(trim($p->address_line)) === $hint)
                        ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'address' => $p->address_line])->values()->all();
                }
                $values = collect($detected)->except(['evidence', 'warnings'])->all();
                // Even a single suggestion requires explicit property selection; no arbitrary match.
                $values = [...$values, 'property_id' => null, ...($extraction->kind === 'invoice' ? ['status' => 'pending']
                    : ['contact_ids' => [], 'mode' => 'create', 'lease_id' => null])];
                $extraction->update(['status' => 'needs_review', 'error_code' => null,
                    'draft' => ['detected' => $detected, 'values' => $values, 'property_candidates' => $candidates]]);
                $run->update(['status' => 'completed', 'finished_at' => now()]);
            });
        } catch (Throwable) {
            if ($step && AiRun::whereKey($step->run_id)->exists()) {
                app(AiRunLedger::class)->failCall($step, $error, (int) ((microtime(true) - $started) * 1000));
            }
            AiDocumentExtraction::whereKey($this->extractionId)->where('status', 'processing')
                ->update(['status' => 'failed', 'error_code' => $error, 'draft' => null]);
            $run?->update(['status' => 'failed', 'error_code' => $error, 'finished_at' => now()]);
            // No provider exception/body/file contents in application or failed-job logs.
        }
    }

    public function failed(?Throwable $exception): void
    {
        $extraction = AiDocumentExtraction::find($this->extractionId);
        if ($extraction?->status === 'processing') {
            $extraction->update(['status' => 'failed', 'error_code' => 'timeout', 'draft' => null]);
            $run = AiRun::find($extraction->run_id);
            $run?->update(['status' => 'failed', 'error_code' => 'timeout', 'cost_incomplete' => true, 'finished_at' => now()]);
        }
    }

    private function context(AiDocumentExtraction $extraction): array
    {
        $user = User::findOrFail($extraction->user_id);
        $portfolio = Portfolio::findOrFail($extraction->portfolio_id);
        app(DocumentAiAccess::class)->assertAvailable($user, $portfolio);
        abort_unless($extraction->notice_version === config('ai_documents.notice_version') && $extraction->expires_at->isFuture(), 403);
        $document = Document::where('portfolio_id', $portfolio->id)->findOrFail($extraction->document_id);

        return [$user, $portfolio, $document];
    }
}
