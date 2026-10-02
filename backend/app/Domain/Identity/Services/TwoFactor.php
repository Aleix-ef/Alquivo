<?php

namespace App\Domain\Identity\Services;

use App\Models\User;
use App\Support\OutboundMail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactor
{
    public function emailDeliveryAvailable(): bool
    {
        return app(OutboundMail::class)->available();
    }

    public function binding(User $user): string
    {
        return hash_hmac('sha256', implode('|', [
            $user->getAuthPassword(),
            $user->two_factor_secret ?? '',
            $user->two_factor_method ?: 'authenticator',
            $user->email,
        ]), config('app.key'));
    }

    /** Call before the transaction so failed attempts survive database-cache rollbacks. */
    public function throttle(int $userId): void
    {
        $limit = 'two-factor:'.$userId;
        abort_if(RateLimiter::tooManyAttempts($limit, 5), 429, 'Demasiados intentos. Espera un minuto antes de volver a intentarlo.');
        RateLimiter::hit($limit, 60);
    }

    /** Caller MUST hold the user row lock until the surrounding transaction commits. */
    public function consume(User $user, string $code): string
    {
        $step = preg_match('/^\d{6}$/D', $code)
            ? (new Google2FA)->verifyKeyNewer($user->two_factor_secret, $code, $user->two_factor_last_step ?? 0, 1, intdiv(now()->timestamp, 30)) : false;
        if ($step !== false) {
            $user->forceFill(['two_factor_last_step' => $step])->save();

            return 'authenticator';
        }
        $codes = $user->two_factor_recovery_codes ?? [];
        $hashed = hash('sha256', $code);
        foreach ($codes as $index => $hash) {
            if (hash_equals($hash, $hashed)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return 'recovery';
            }
        }
        throw ValidationException::withMessages(['code' => ['El código no es válido o ya se ha usado. Si acabas de activar el autenticador, espera a que cambie el código (puede tardar hasta 30 segundos) y vuelve a intentarlo. También puedes usar un código de recuperación.']]);
    }
}
