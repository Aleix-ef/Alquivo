<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Models\AiActionProposal;
use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Proposals are data, never executable model instructions. Only the HTTP confirmation executes. */
final class ActionProposalService
{
    public function __construct(
        private readonly ProposalActionRegistry $actions,
        private readonly AiCapabilities $capabilities,
    ) {}

    public function proposeExpense(Portfolio $portfolio, User $user, AiRun $run, array $input): AiActionProposal
    {
        return $this->propose('expense', $portfolio, $user, $run, $input);
    }

    public function proposeContactPhone(Portfolio $portfolio, User $user, AiRun $run, array $input): AiActionProposal
    {
        return $this->propose('contact_phone', $portfolio, $user, $run, $input);
    }

    public function proposeRentPayment(Portfolio $portfolio, User $user, AiRun $run, array $input): AiActionProposal
    {
        return $this->propose('rent_payment', $portfolio, $user, $run, $input);
    }

    public function proposePropertyNote(Portfolio $portfolio, User $user, AiRun $run, array $input): AiActionProposal
    {
        return $this->propose('property_note', $portfolio, $user, $run, $input);
    }

    public function proposeCreation(string $type, Portfolio $portfolio, User $user, AiRun $run, array $input): AiActionProposal
    {
        abort_unless(isset(CreationProposalActions::FIELDS[$type]), 422);

        return $this->propose($type, $portfolio, $user, $run, $input);
    }

    private function propose(string $type, Portfolio $portfolio, User $user, AiRun $run, array $input): AiActionProposal
    {
        return DB::transaction(function () use ($type, $portfolio, $user, $run, $input) {
            [$portfolio, $user] = $this->lockedContext($portfolio, $user);
            $this->assertActions($portfolio, $user);
            $run = AiRun::query()->where('portfolio_id', $portfolio->id)->where('user_id', $user->id)
                ->lockForUpdate()->findOrFail($run->id);
            $this->assertConversation($run, $portfolio, $user);
            $payload = $this->actions->validatedData($type, $portfolio, $user, $input);
            $hash = $this->payloadHash($payload);
            $existing = AiActionProposal::query()->where('run_id', $run->id)->where('type', $type)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Ya hay una propuesta en esta consulta. Revísala antes de preparar otra.');

                return $existing;
            }

            $proposal = AiActionProposal::create([
                'run_id' => $run->id, 'portfolio_id' => $portfolio->id, 'user_id' => $user->id,
                'type' => $type, 'status' => 'pending', 'revision' => 1,
                'payload' => $payload, 'payload_hash' => $hash,
                // Keep the encrypted legacy column name; it now holds the typed target snapshot.
                'property_snapshot' => $this->actions->snapshot($type, $portfolio, $payload),
                'expires_at' => now()->addMinutes((int) config('ai.actions.proposal_ttl_minutes', 30)),
            ]);
            $this->audit($proposal, 'created');

            return $proposal;
        });
    }

    public function findOwned(Portfolio $portfolio, User $user, string $id): AiActionProposal
    {
        $this->assertOwner($portfolio, $user);

        return AiActionProposal::query()->where('portfolio_id', $portfolio->id)
            ->where('user_id', $user->id)->findOrFail($id);
    }

    public function editableFields(AiActionProposal $proposal): array
    {
        return $this->actions->editableFields($proposal->type);
    }

    public function revise(Portfolio $portfolio, User $user, string $id, int $revision, array $changes): AiActionProposal
    {
        $proposal = DB::transaction(function () use ($portfolio, $user, $id, $revision, $changes) {
            [$portfolio, $user] = $this->lockedContext($portfolio, $user);
            $proposal = $this->lockedProposal($portfolio, $user, $id);
            $this->assertActions($portfolio, $user);
            $this->assertConversation($proposal->run, $portfolio, $user);
            if ($this->expire($proposal)) {
                return $proposal;
            }
            $this->assertPendingRevision($proposal, $revision);
            abort_unless($proposal->schema_version === 1, 409, 'Esta propuesta ya no es compatible. Prepara una nueva.');
            $this->actions->assertFields($changes, $this->editableFields($proposal));
            $payload = $this->actions->validatedData($proposal->type, $portfolio, $user, [...$proposal->payload, ...$changes]);
            $proposal->forceFill([
                'payload' => $payload, 'payload_hash' => $this->payloadHash($payload),
                'property_snapshot' => $this->actions->snapshot($proposal->type, $portfolio, $payload),
                'revision' => $proposal->revision + 1,
            ])->save();
            $this->audit($proposal, 'revised');

            return $proposal;
        });

        $this->assertNotExpired($proposal);

        return $proposal;
    }

    public function confirm(Portfolio $portfolio, User $user, string $id, int $revision): AiActionProposal
    {
        $proposal = DB::transaction(function () use ($portfolio, $user, $id, $revision) {
            [$portfolio, $user] = $this->lockedContext($portfolio, $user);
            $proposal = $this->lockedProposal($portfolio, $user, $id);
            $this->assertActions($portfolio, $user);
            $this->assertConversation($proposal->run, $portfolio, $user);
            abort_unless($proposal->revision === $revision, 409, 'Esta vista previa ha cambiado. Revísala antes de confirmar.');

            // The row lock + atomic write/receipt is the idempotency boundary, not a cache lock.
            if ($proposal->status === 'executed') {
                return $proposal;
            }
            if ($this->expire($proposal)) {
                return $proposal;
            }
            $this->assertPendingRevision($proposal, $revision);
            abort_unless($this->actions->supports($proposal->type) && $proposal->schema_version === 1, 409, 'Esta propuesta ya no es compatible. Prepara una nueva.');
            // Compare the target first: a changed balance is a stale preview, not an invitation to pay again.
            abort_unless($proposal->property_snapshot === $this->actions->snapshot($proposal->type, $portfolio, $proposal->payload), 409,
                'Los datos de esta propuesta han cambiado. Abre Editar y guarda una vista previa actualizada antes de confirmar.');
            $payload = $this->actions->validatedData($proposal->type, $portfolio, $user, $proposal->payload);
            abort_unless(hash_equals($proposal->payload_hash, $this->payloadHash($payload)), 409, 'La propuesta ha cambiado. Prepara una nueva.');
            $result = $this->actions->execute($proposal->type, $portfolio, $user, $payload);
            $proposal->forceFill([
                'status' => 'executed', 'confirmed_by' => $user->id,
                'confirmed_at' => now(), 'executed_at' => now(),
                'result' => $result,
            ])->save();
            $this->audit($proposal, 'executed', array_diff_key($result, ['path' => true]));

            return $proposal;
        }, 3);

        $this->assertNotExpired($proposal);

        return $proposal;
    }

    public function cancel(Portfolio $portfolio, User $user, string $id, int $revision): AiActionProposal
    {
        return DB::transaction(function () use ($portfolio, $user, $id, $revision) {
            [$portfolio, $user] = $this->lockedContext($portfolio, $user);
            $proposal = $this->lockedProposal($portfolio, $user, $id);
            abort_unless($proposal->revision === $revision, 409, 'Esta vista previa ha cambiado. Recárgala antes de cancelar.');
            if ($proposal->status === 'cancelled' || $this->expire($proposal)) {
                return $proposal;
            }
            $this->assertPendingRevision($proposal, $revision);
            $proposal->forceFill(['status' => 'cancelled'])->save();
            $this->audit($proposal, 'cancelled');

            return $proposal;
        });
    }

    public function present(AiActionProposal $proposal): array
    {
        $payload = $proposal->payload;
        $snapshot = $proposal->property_snapshot;

        return [
            'id' => $proposal->id, 'type' => $proposal->type,
            'status' => $proposal->status === 'pending' && $proposal->expires_at->isPast() ? 'expired' : $proposal->status,
            'revision' => $proposal->revision, 'expires_at' => $proposal->expires_at->toIso8601String(),
            'preview' => $this->actions->preview($proposal->type, $payload, $snapshot),
            'result' => $proposal->result,
        ];
    }

    private function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function lockedContext(Portfolio $portfolio, User $user): array
    {
        $user = User::query()->lockForUpdate()->findOrFail($user->id);
        $portfolio = Portfolio::query()->lockForUpdate()->findOrFail($portfolio->id);
        $this->assertOwner($portfolio, $user);

        return [$portfolio, $user];
    }

    private function lockedProposal(Portfolio $portfolio, User $user, string $id): AiActionProposal
    {
        return AiActionProposal::query()->where('portfolio_id', $portfolio->id)
            ->where('user_id', $user->id)->lockForUpdate()->findOrFail($id);
    }

    private function assertOwner(Portfolio $portfolio, User $user): void
    {
        abort_unless($portfolio->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists(), 404);
    }

    private function assertActions(Portfolio $portfolio, User $user): void
    {
        abort_unless($this->capabilities->allowsActions($portfolio, $user), 403, 'Las acciones del asistente no están disponibles. Revisa tu plan y la activación del asistente.');
    }

    private function assertConversation(?AiRun $run, Portfolio $portfolio, User $user): void
    {
        abort_unless($run && AiConversation::query()->whereKey($run->conversation_id)
            ->where('portfolio_id', $portfolio->id)->where('user_id', $user->id)->exists(), 409,
            'Esta conversación ya no está disponible. Prepara una propuesta nueva.');
    }

    private function assertPendingRevision(AiActionProposal $proposal, int $revision): void
    {
        abort_unless($proposal->status === 'pending', 409, 'Esta propuesta ya no está pendiente.');
        abort_unless($proposal->revision === $revision, 409, 'Esta vista previa ha cambiado. Revísala antes de confirmar.');
    }

    /** Commit expiry before returning a conflict to the caller. */
    private function expire(AiActionProposal $proposal): bool
    {
        if ($proposal->status === 'pending' && $proposal->expires_at->isPast()) {
            $proposal->forceFill(['status' => 'expired'])->save();
            $this->audit($proposal, 'expired');
        }

        return $proposal->status === 'expired';
    }

    private function assertNotExpired(AiActionProposal $proposal): void
    {
        abort_if($proposal->status === 'expired', 409, 'Esta propuesta ha caducado. Pide al asistente que prepare una nueva.');
    }

    private function audit(AiActionProposal $proposal, string $status, array $metadata = []): void
    {
        DB::table('ai_run_steps')->insert([
            'run_id' => $proposal->run_id, 'kind' => 'action', 'status' => $status,
            'tool' => 'propose_'.$proposal->type,
            'metadata' => json_encode([
                'proposal_id' => $proposal->id, 'revision' => $proposal->revision,
                'actor_id' => $proposal->user_id, ...$metadata,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
