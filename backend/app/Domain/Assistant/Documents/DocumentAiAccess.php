<?php

namespace App\Domain\Assistant\Documents;

use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;

final class DocumentAiAccess
{
    public function available(User $user, Portfolio $portfolio): bool
    {
        return config('ai_documents.enabled') && config('assistant.enabled') && $user->local_admin
            && $portfolio->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists();
    }

    public function assertAvailable(User $user, Portfolio $portfolio, bool $consent = true): void
    {
        abort_unless($this->available($user, $portfolio), 404);
        if ($consent) {
            abort_unless($user->document_ai_accepted_at
                && $user->document_ai_notice_version === config('ai_documents.notice_version'), 403,
                'Acepta el aviso documental vigente antes de continuar.');
        }
    }
}
