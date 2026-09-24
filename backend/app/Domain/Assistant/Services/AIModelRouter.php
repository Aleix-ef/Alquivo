<?php

namespace App\Domain\Assistant\Services;

use RuntimeException;

final class AIModelRouter
{
    public function route(string $profile = 'chat'): array
    {
        $profile = $profile === 'chat' ? (string) config('ai.routing.chat', 'legacy') : $profile;
        if (! array_key_exists($profile, config('ai.routing', [])) || $profile === 'chat') {
            throw new RuntimeException('Perfil de IA no configurado.');
        }
        if ($profile === 'exceptional' && ! config('ai.exceptional_enabled')) {
            throw new RuntimeException('El perfil excepcional está desactivado.');
        }
        $model = $profile === 'legacy' ? (string) config('assistant.model') : config('ai.routing.'.$profile);
        $pricing = config('ai.models')[$model] ?? null;
        if (! $pricing && ! app()->environment('testing')) {
            throw new RuntimeException('Configura la tarifa del modelo antes de utilizarlo.');
        }

        return [
            'provider' => 'openai', 'model' => $model, 'profile' => $profile,
            'max_output_tokens' => (int) config('assistant.max_output_tokens'), 'pricing' => $pricing,
        ];
    }
}
