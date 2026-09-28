<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Documents\DocumentAiAccess;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PlanService;
use App\Models\User;
use App\Support\ProductFeatures;

final class AiCapabilities
{
    public function forUser(Portfolio $portfolio, User $user): array
    {
        return [
            'chat' => app(ProductFeatures::class)->assistant($user),
            'actions' => $this->allowsActions($portfolio, $user),
            'documents' => app(DocumentAiAccess::class)->available($user, $portfolio),
            'intelligence' => false, 'automation' => false,
        ];
    }

    public function allowsActions(Portfolio $portfolio, User $user): bool
    {
        $plan = app(PlanService::class)->effectiveCode($portfolio);
        $plan = $plan !== 'beta' && $portfolio->trial_ends_at?->isFuture() ? 'trial' : $plan;

        return config('ai.actions.enabled') && config('assistant.enabled')
            && ($user->local_admin || config('beta.assistant_validated'))
            && $user->assistant_enabled_at !== null
            && $user->assistant_notice_version === config('assistant.notice_version')
            && $portfolio->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists()
            && in_array($plan, config('ai.actions.plans', []), true);
    }

    public function assertActions(Portfolio $portfolio, User $user): void
    {
        abort_unless($this->allowsActions($portfolio, $user), 403, 'Las acciones del asistente no están disponibles para esta cuenta.');
    }
}
