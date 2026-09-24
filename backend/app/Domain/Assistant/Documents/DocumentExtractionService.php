<?php

namespace App\Domain\Assistant\Documents;

use App\Domain\Assistant\Jobs\ExtractDocument;
use App\Domain\Assistant\Models\AiDocumentExtraction;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Services\AssistantUsageService;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\PrivateFileVault;
use App\Domain\Finance\Actions\CreateExpense;
use App\Domain\Leasing\Actions\CreateLease;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PropertyAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class DocumentExtractionService
{
    public function start(User $user, Portfolio $portfolio, Document $document, string $kind): AiDocumentExtraction
    {
        app(DocumentAiAccess::class)->assertAvailable($user, $portfolio);
        abort_unless($document->portfolio_id === $portfolio->id, 404);
        abort_unless(in_array($kind, ['invoice', 'contract'], true), 422);
        // File parsing and scanning run in the worker. Here we only hash the bounded encrypted original.
        abort_if($document->size > config('ai_documents.max_bytes'), 422, 'El archivo supera el límite de tamaño.');
        abort_unless(in_array($document->mime_type, config('ai_documents.mime_types'), true), 422, 'Formato no admitido.');
        $bytes = app(PrivateFileVault::class)->read($document->storage_key);
        $hash = hash_hmac('sha256', $bytes, config('app.key'));

        return DB::transaction(function () use ($user, $portfolio, $document, $kind, $hash) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $portfolio = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
            app(DocumentAiAccess::class)->assertAvailable($user, $portfolio);
            $document = Document::where('portfolio_id', $portfolio->id)->lockForUpdate()->findOrFail($document->id);
            $key = ['portfolio_id' => $portfolio->id, 'file_hash' => $hash, 'kind' => $kind, 'schema_version' => config('ai_documents.schema_version')];
            $existing = AiDocumentExtraction::where($key)->first();
            if ($existing) {
                abort_unless($existing->user_id === $user->id, 409, 'El documento ya tiene una revisión de otro propietario.');

                return $existing;
            }
            abort_if($document->transaction_id || $document->lease_id, 409, 'Este documento ya está vinculado a una operación o contrato.');
            app(AssistantUsageService::class)->reserve($portfolio, $user);
            $extraction = AiDocumentExtraction::create([...$key, 'document_id' => $document->id, 'user_id' => $user->id,
                'notice_version' => config('ai_documents.notice_version'), 'expires_at' => now()->addDays(config('ai_documents.retention_days'))]);
            ExtractDocument::dispatch($extraction->id)->afterCommit();

            return $extraction->fresh();
        });
    }

    public function owned(User $user, Portfolio $portfolio, string $id): AiDocumentExtraction
    {
        app(DocumentAiAccess::class)->assertAvailable($user, $portfolio, false);

        return AiDocumentExtraction::where('user_id', $user->id)->where('portfolio_id', $portfolio->id)->findOrFail($id);
    }

    public function present(AiDocumentExtraction $extraction): array
    {
        // Expire payload, but retain the minimal idempotency receipt while the document exists.
        if ($extraction->expires_at->isPast()) {
            $extraction->update(['status' => $extraction->status === 'confirmed' ? 'confirmed' : 'expired', 'draft' => null]);
        }

        return [...$extraction->toArray(), 'draft' => $extraction->draft, 'simulated' => true];
    }

    public function mutate(User $user, Portfolio $portfolio, string $id, string $operation, array $input): AiDocumentExtraction
    {
        abort_unless(in_array($operation, ['revise', 'confirm', 'cancel', 'retry'], true), 404);

        return DB::transaction(function () use ($user, $portfolio, $id, $operation, $input) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $portfolio = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();
            $extraction = $this->owned($user, $portfolio, $id);
            $document = Document::where('portfolio_id', $portfolio->id)->lockForUpdate()->findOrFail($extraction->document_id);
            $extraction = AiDocumentExtraction::whereKey($id)->lockForUpdate()->firstOrFail();
            $allowed = $operation === 'revise' ? ['revision', 'values'] : ['revision'];
            abort_if(array_diff(array_keys($input), $allowed), 422, 'Campos no permitidos.');
            Validator::make($input, ['revision' => ['required', 'integer', 'min:1']])->validate();
            if ($operation === 'confirm' && $extraction->status === 'confirmed') {
                return $extraction;
            }
            abort_unless($extraction->revision === (int) $input['revision'], 409, 'La revisión ha cambiado. Recarga el borrador.');
            if ($operation === 'cancel') {
                abort_if($extraction->status === 'confirmed', 409);
                $extraction->update(['status' => 'cancelled', 'draft' => null]);

                return $extraction;
            }
            app(DocumentAiAccess::class)->assertAvailable($user, $portfolio);
            abort_unless($extraction->notice_version === config('ai_documents.notice_version') && $extraction->expires_at->isFuture(), 409, 'El borrador ha caducado.');
            if ($operation === 'retry') {
                abort_unless($extraction->status === 'failed' && $extraction->attempts < config('ai_documents.max_attempts'), 409, 'No se puede reintentar este análisis.');
                app(AssistantUsageService::class)->reserve($portfolio, $user);
                $extraction->update(['status' => 'queued', 'error_code' => null, 'revision' => $extraction->revision + 1]);
                ExtractDocument::dispatch($id)->afterCommit();

                return $extraction;
            }
            abort_unless($extraction->status === 'needs_review', 409, 'El borrador no está listo para confirmar.');
            $draft = $extraction->draft;
            if ($operation === 'revise') {
                $values = $extraction->kind === 'invoice' ? $this->invoiceValues($input['values'] ?? [], false)
                    : $this->contractValues($input['values'] ?? [], false);
                $draft['values'] = $values;
                $extraction->update(['draft' => $draft, 'revision' => $extraction->revision + 1, 'reviewed_at' => now()]);

                return $extraction;
            }
            // The worker already parsed/scanned these exact bytes. Confirmation performs no
            // PDF parsing, provider call or scanner network request while holding domain locks.
            $bytes = app(PrivateFileVault::class)->read($document->storage_key);
            abort_unless(hash_equals($extraction->file_hash, hash_hmac('sha256', $bytes, config('app.key'))), 409, 'El archivo ha cambiado.');
            abort_if($document->transaction_id || $document->lease_id, 409, 'El documento ya está vinculado.');
            $values = $extraction->kind === 'invoice' ? $this->invoiceValues($draft['values'], true) : $this->contractValues($draft['values'], true);
            $receipt = $extraction->kind === 'invoice' ? $this->confirmInvoice($user, $portfolio, $document, $values)
                : $this->confirmContract($user, $portfolio, $document, $values);
            $extraction->update(['status' => 'confirmed', 'receipt' => $receipt, 'confirmed_at' => now()]);
            $run = AiRun::findOrFail($extraction->run_id);
            $run->steps()->create(['kind' => 'action', 'status' => 'executed', 'tool' => 'confirm_document_'.$extraction->kind, 'metadata' => $receipt]);

            return $extraction;
        });
    }

    private function confirmInvoice(User $user, Portfolio $portfolio, Document $document, array $values): array
    {
        abort_unless($values['currency'] === $portfolio->currency, 422, 'La moneda debe coincidir con la cartera. No se realizan conversiones automáticas.');
        $transaction = app(CreateExpense::class)->execute($portfolio, $user, [
            'property_id' => $values['property_id'], 'category' => $values['category'], 'description' => $values['description'],
            'amount' => $values['total'], 'transaction_date' => $values['date'], 'status' => $values['status'],
        ]);
        $document->update(['transaction_id' => $transaction->id, 'property_id' => $transaction->property_id, 'category' => 'invoice']);

        return ['transaction_id' => $transaction->id, 'document_id' => $document->id];
    }

    private function confirmContract(User $user, Portfolio $portfolio, Document $document, array $values): array
    {
        if ($values['mode'] === 'link') {
            $lease = Lease::where('portfolio_id', $portfolio->id)->lockForUpdate()->findOrFail($values['lease_id']);
            app(PropertyAccess::class)->assertWritable($portfolio, $lease->property_id);
        } else {
            abort_unless($values['currency'] === $portfolio->currency, 422, 'La moneda no coincide con la cartera.');
            abort_unless($values['periodicity'] === 'monthly', 422, 'Alquivo gestiona rentas mensuales. No convierte automáticamente otra periodicidad.');
            $lease = app(CreateLease::class)->execute($portfolio, $user, [
                'property_id' => $values['property_id'], 'contact_ids' => $values['contact_ids'], 'status' => 'draft',
                'start_date' => $values['start_date'], 'end_date' => $values['end_date'],
                'monthly_rent' => $values['monthly_rent'], 'deposit_amount' => $values['deposit_amount'], 'payment_day' => $values['payment_day'],
            ]);
        }
        $document->update(['lease_id' => $lease->id, 'property_id' => $lease->property_id, 'category' => 'contract']);

        return ['lease_id' => $lease->id, 'document_id' => $document->id, 'created' => $values['mode'] === 'create'];
    }

    public function contractValues(array $values, bool $complete): array
    {
        $fields = [...DocumentExtractionSchema::CONTRACT, 'participants', 'property_id', 'contact_ids', 'mode', 'lease_id'];
        abort_if(array_diff(array_keys($values), $fields), 422, 'Campos no permitidos.');
        $required = $complete && ($values['mode'] ?? null) === 'create' ? 'required' : 'nullable';
        $rules = array_fill_keys(DocumentExtractionSchema::CONTRACT, ['nullable', 'string', 'max:1000']);
        foreach (['monthly_rent', 'deposit_amount'] as $field) {
            $rules[$field] = [$required, 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D'];
        }
        $rules['start_date'] = [$required, 'date_format:Y-m-d'];
        $rules['end_date'] = ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'];
        $rules['currency'] = [$required, 'regex:/^[A-Z]{3}$/D'];
        $rules['periodicity'] = [$required, Rule::in(['monthly', 'yearly', 'weekly', 'other'])];
        $rules['payment_day'] = [$required, 'integer', 'between:1,28'];
        $rules['mode'] = ['required', Rule::in(['create', 'link'])];
        $rules['lease_id'] = [$complete && ($values['mode'] ?? null) === 'link' ? 'required' : 'nullable', 'integer', 'min:1'];
        $rules['property_id'] = [$required, 'integer', 'min:1'];
        $rules['contact_ids'] = [$required, 'array', $required === 'required' ? 'min:1' : 'min:0', 'max:20'];
        $rules['contact_ids.*'] = ['integer', 'min:1', 'distinct'];
        $rules['participants'] = ['present', 'array', 'max:20'];
        $rules['participants.*'] = ['array:name,role'];
        $rules['participants.*.name'] = ['nullable', 'string', 'max:150'];
        $rules['participants.*.role'] = ['nullable', Rule::in(['tenant', 'landlord', 'guarantor', 'other'])];
        $data = Validator::make($values, $rules)->validate();

        return [...array_fill_keys($fields, null), ...$data];
    }

    public function invoiceValues(array $values, bool $complete): array
    {
        $fields = [...DocumentExtractionSchema::INVOICE, 'property_id', 'status'];
        abort_if(array_diff(array_keys($values), $fields), 422, 'Campos no permitidos.');
        $required = $complete ? 'required' : 'nullable';
        $rules = array_fill_keys(DocumentExtractionSchema::INVOICE, ['nullable', 'string', 'max:1000']);
        foreach (['total', 'subtotal', 'vat'] as $field) {
            $rules[$field] = [$field === 'total' ? $required : 'nullable', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D'];
        }
        $rules['date'] = [$required, 'date_format:Y-m-d'];
        $rules['description'] = [$required, 'string', 'max:180'];
        $rules['category'] = [$required, Rule::in(['maintenance', 'insurance', 'tax', 'other'])];
        $rules['currency'] = [$required, 'regex:/^[A-Z]{3}$/D'];
        $rules['property_id'] = ['nullable', 'integer', 'min:1'];
        $rules['status'] = ['required', Rule::in(['pending', 'paid'])];
        $data = Validator::make($values, $rules)->validate();

        return [...array_fill_keys($fields, null), ...$data];
    }
}
