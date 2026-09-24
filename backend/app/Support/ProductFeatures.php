<?php

namespace App\Support;

use App\Models\User;

final class ProductFeatures
{
    public function betaProgram(): bool
    {
        return (bool) config('beta.program_enabled');
    }

    public function billing(): bool
    {
        return ! $this->betaProgram() && (bool) config('beta.billing_enabled');
    }

    public function assistant(?User $user = null): bool
    {
        return ($user?->local_admin || config('beta.assistant_validated')) && config('assistant.enabled') && filled(config('services.openai.key'));
    }

    public function fiscality(?User $user = null): bool
    {
        return $user?->local_admin || (bool) config('beta.fiscality_enabled');
    }

    public function publicConfiguration(): array
    {
        $email = config('support.email');

        return [
            'beta_program' => $this->betaProgram(),
            'billing_enabled' => $this->billing(),
            'assistant' => $this->assistant(), 'fiscality' => $this->fiscality(),
            'support_email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
        ];
    }

    public function plan(array $plan, ?User $user = null): array
    {
        $plan['features'] = array_values(array_filter($plan['features'], fn ($feature) => ($user?->local_admin || $this->assistant() || ! str_starts_with($feature, 'Asistente'))
            && ($this->fiscality($user) || ! str_starts_with($feature, 'Fiscalidad'))));
        if (! $this->fiscality($user)) {
            $plan['entitlements'] = array_values(array_diff($plan['entitlements'] ?? [], ['fiscal_reports']));
        }

        return $plan;
    }
}
