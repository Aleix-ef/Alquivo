<?php

namespace App\Domain\Identity\Services;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** Session/account/action-bound codes for enrollment and authenticated security changes. */
final class TwoFactorEmailCodes
{
    public function issue(User $user, Request $request, string $purpose): void
    {
        abort_unless($request->hasSession(), 422, 'Utiliza una sesión de navegador.');
        abort_unless($user->hasVerifiedEmail(), 422, 'Confirma primero el correo de tu cuenta.');
        abort_unless(app(TwoFactor::class)->emailDeliveryAvailable(), 503, 'El envío de códigos por correo no está disponible.');
        $key = 'two-factor-email:'.$user->id;
        abort_if(RateLimiter::tooManyAttempts($key, 3), 429, 'Espera unos minutos antes de solicitar otro código.');
        RateLimiter::hit($key, 300);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // Replace before sending: a failed resend cannot keep an older code usable.
        $request->session()->forget('two_factor_security_code');
        try {
            Notification::route('mail', $user->email)->notify(new TwoFactorCodeNotification($code, 'security'));
        } catch (\Throwable) {
            Log::warning('Two-factor security email delivery failed');
            abort(503, 'No hemos podido enviar el código. Inténtalo más tarde.');
        }
        $request->session()->put('two_factor_security_code', [
            'user_id' => $user->id, 'purpose' => $purpose,
            'binding' => app(TwoFactor::class)->binding($user),
            'hash' => $this->hash($user, $purpose, $code), 'expires' => now()->addMinutes(5)->timestamp, 'attempts' => 0,
        ]);
    }

    /** Caller holds the user row lock; session consumption survives a DB rollback. */
    public function consume(User $user, Request $request, string $purpose, string $code): void
    {
        $pending = $request->session()->get('two_factor_security_code');
        if (is_array($pending)) {
            abort_if(($pending['attempts'] ?? 0) >= 5, 429, 'Solicita un código nuevo: has agotado sus intentos.');
            $pending['attempts'] = ($pending['attempts'] ?? 0) + 1;
            $request->session()->put('two_factor_security_code', $pending);
        }
        $key = 'two-factor-security-attempts:'.$user->id;
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Demasiados intentos. Espera unos minutos y solicita otro código.');
        RateLimiter::hit($key, 300);
        $valid = is_array($pending) && $user->hasVerifiedEmail()
            && $pending['user_id'] === $user->id && $pending['purpose'] === $purpose
            && $pending['expires'] > now()->timestamp
            && hash_equals($pending['binding'], app(TwoFactor::class)->binding($user))
            && preg_match('/^\d{6}$/D', $code) && hash_equals($pending['hash'], $this->hash($user, $purpose, $code));
        if (! $valid) {
            throw ValidationException::withMessages(['code' => ['El código del correo no es válido, ha caducado o corresponde a otra operación.']]);
        }
        $request->session()->forget('two_factor_security_code');
    }

    private function hash(User $user, string $purpose, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.$purpose.'|'.$code, (string) config('app.key'));
    }
}
