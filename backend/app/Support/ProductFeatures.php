<?php

namespace App\Support;

final class ProductFeatures
{
    public function assistant(): bool
    {
        return config('beta.assistant_validated') && config('assistant.enabled') && filled(config('services.openai.key'));
    }

    public function fiscality(): bool
    {
        return (bool) config('beta.fiscality_enabled');
    }

    public function publicConfiguration(): array
    {
        $email = config('support.email');

        return [
            'billing_enabled' => (bool) config('beta.billing_enabled'),
            'assistant' => $this->assistant(), 'fiscality' => $this->fiscality(),
            'support_email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
        ];
    }

    public function plan(array $plan): array
    {
        $plan['features'] = array_values(array_filter($plan['features'], fn ($feature) => ($this->assistant() || ! str_starts_with($feature, 'Asistente'))
            && ($this->fiscality() || ! str_starts_with($feature, 'Fiscalidad'))));
        if (! $this->fiscality()) {
            $plan['entitlements'] = array_values(array_diff($plan['entitlements'] ?? [], ['fiscal_reports']));
        }

        return $plan;
    }
}
