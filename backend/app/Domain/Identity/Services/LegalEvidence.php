<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\LegalAcceptance;
use App\Models\User;
use Illuminate\Support\Carbon;

final class LegalEvidence
{
    // Call inside the same transaction as the state change, with the user row locked
    // (registration creates a new row). Never manufacture historical acceptances.
    public function accept(User $user, string $scope, string $version): void
    {
        $active = LegalAcceptance::where('user_id', $user->id)->where('scope', $scope)->whereNull('expires_at');
        if ((clone $active)->where('action', 'accepted')->where('version', $version)->exists()) {
            return;
        }
        $active->update(['expires_at' => $this->expiry()]);
        $this->record($user, $scope, 'accepted', $version);
    }

    public function withdraw(User $user, string $scope, ?string $version): void
    {
        LegalAcceptance::where('user_id', $user->id)->where('scope', $scope)->whereNull('expires_at')
            ->update(['expires_at' => $this->expiry()]);
        if ($version !== null) {
            $this->record($user, $scope, 'withdrawn', $version, $this->expiry());
        }
    }

    public function closeAccount(User $user): void
    {
        $this->withdraw($user, 'assistant', $user->assistant_notice_version);
        $this->withdraw($user, 'document_ai', $user->document_ai_notice_version);
        LegalAcceptance::where('user_id', $user->id)->whereNull('expires_at')->update(['expires_at' => $this->expiry()]);
    }

    private function record(User $user, string $scope, string $action, string $version, mixed $expiry = null): void
    {
        LegalAcceptance::create([
            'user_id' => $user->id, 'subject_email' => mb_strtolower($user->email),
            'scope' => $scope, 'action' => $action, 'version' => $version,
            'recorded_at' => now(), 'expires_at' => $expiry,
        ]);
    }

    private function expiry(): Carbon
    {
        return now()->addDays((int) config('legal.evidence_retention_days'));
    }
}
