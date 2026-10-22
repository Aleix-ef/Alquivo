<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\TrustedDevice;
use App\Domain\Identity\Services\RevokeSessions;
use App\Domain\Identity\Services\TrustedDevices;
use App\Domain\Identity\Services\TwoFactor;
use App\Domain\Identity\Services\TwoFactorEmailCodes;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function status(Request $request): array
    {
        return ['enabled' => (bool) $request->user()->two_factor_confirmed_at,
            'method' => $request->user()->two_factor_confirmed_at ? ($request->user()->two_factor_method ?: 'authenticator') : 'email',
            'authenticator_configured' => filled($request->user()->two_factor_secret),
            'email_verified' => $request->user()->hasVerifiedEmail(), 'email_delivery_available' => app(TwoFactor::class)->emailDeliveryAvailable(),
            'email_hint' => $this->maskedEmail($request->user()->email), 'recovery_codes_remaining' => count($request->user()->two_factor_recovery_codes ?? [])];
    }

    public function setup(Request $request): array
    {
        abort_unless($request->hasSession(), 422, 'Utiliza la sesión de navegador para configurar el doble factor.');
        $request->validate(['current_password' => ['required', 'current_password:web'], 'method' => ['sometimes', 'in:email,authenticator,both'], 'code' => ['nullable', 'string', 'max:40']]);
        $method = $request->input('method', 'email');
        if ($request->user()->two_factor_confirmed_at) {
            app(TwoFactor::class)->throttle($request->user()->id);
        }
        $pending = DB::transaction(function () use ($request, $method) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless(Hash::check($request->input('current_password'), $user->password), 422);
            if (in_array($method, ['email', 'both'], true)) {
                abort_unless($user->hasVerifiedEmail(), 422, 'Confirma primero el correo de tu cuenta.');
                abort_unless(app(TwoFactor::class)->emailDeliveryAvailable(), 503, 'El envío de códigos por correo no está disponible.');
            }
            $adding = (bool) $user->two_factor_confirmed_at;
            if ($adding) {
                abort_unless(! filled($user->two_factor_secret) && in_array($method, ['authenticator', 'both'], true), 409, 'El doble factor ya está activo.');
                app(TwoFactor::class)->consumeForSecurity($user, $request, 'change_method:'.$method, trim((string) $request->input('code')));
            }
            $emailRequired = $method === 'email' || ($method === 'both' && ! $adding);
            if ($emailRequired) {
                app(TwoFactorEmailCodes::class)->issue($user, $request, 'setup');
            }

            return ['secret' => $method === 'email' ? null : (new Google2FA)->generateSecretKey(),
                'user_id' => $user->id, 'binding' => app(TwoFactor::class)->binding($user), 'method' => $method,
                'adding' => $adding, 'email_required' => $emailRequired, 'expires' => now()->addMinutes(10)->timestamp];
        });
        $request->session()->put('two_factor_setup', $pending);

        return ['method' => $method, 'secret' => $pending['secret'], 'email_confirmation_required' => $pending['email_required'],
            'email_hint' => $this->maskedEmail($request->user()->email)];
    }

    public function confirm(Request $request)
    {
        abort_unless($request->hasSession(), 422, 'Utiliza la sesión de navegador para confirmar el doble factor.');
        $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/D'], 'email_code' => ['nullable', 'string', 'regex:/^\d{6}$/D'], 'remember_device' => ['sometimes', 'boolean']]);
        $pending = $request->session()->get('two_factor_setup');
        abort_unless($pending && $pending['expires'] > now()->timestamp && $pending['user_id'] === $request->user()->id, 422, 'La configuración ha caducado. Empieza de nuevo.');
        app(TwoFactor::class)->throttle($request->user()->id);
        $codes = DB::transaction(function () use ($request, $pending) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($pending['binding'], app(TwoFactor::class)->binding($user)), 422, 'La cuenta ha cambiado. Empieza de nuevo.');
            abort_unless((bool) $user->two_factor_confirmed_at === $pending['adding'], 409, 'La configuración de seguridad ha cambiado.');
            $step = null;
            if ($pending['method'] !== 'email') {
                $step = (new Google2FA)->verifyKeyNewer($pending['secret'], $request->input('code'), 0, 1, intdiv(now()->timestamp, 30));
                abort_unless($step !== false, 422, 'El código del autenticador no es válido.');
            }
            if ($pending['email_required']) {
                app(TwoFactorEmailCodes::class)->consume($user, $request, 'setup',
                    $pending['method'] === 'email' ? $request->input('code') : (string) $request->input('email_code'));
            }
            $codes = array_map(fn () => bin2hex(random_bytes(10)), range(1, 8));
            $user->forceFill(['two_factor_secret' => $pending['secret'], 'two_factor_last_step' => $step,
                'two_factor_method' => $pending['method'], 'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => array_map(fn ($code) => hash('sha256', $code), $codes)])->save();
            app(RevokeSessions::class)->execute($user, $request->session()->getId());

            return $codes;
        });
        $request->session()->forget('two_factor_setup');
        $request->session()->regenerate();
        SecurityAudit::record('account.two_factor_enabled', $request->user()->id);

        $response = response()->json(['recovery_codes' => $codes, 'method' => $pending['method']]);
        if ($request->boolean('remember_device')) {
            $response->withCookie(app(TrustedDevices::class)->issue($request->user()->fresh(), $request));
        }

        return $response;
    }

    public function disable(Request $request)
    {
        abort_unless($request->hasSession(), 422, 'Utiliza la sesión de navegador para gestionar el doble factor.');
        $request->validate(['current_password' => ['required', 'current_password:web'], 'code' => ['required', 'string', 'max:40']]);
        app(TwoFactor::class)->throttle($request->user()->id);
        DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->two_factor_confirmed_at, 409, 'El doble factor no está activo.');
            abort_unless(Hash::check($request->input('current_password'), $user->password), 422);
            app(TwoFactor::class)->consumeForSecurity($user, $request, 'disable', trim($request->input('code')));
            $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null, 'two_factor_method' => 'email'])->save();
            TrustedDevice::where('user_id', $user->id)->delete();
            app(RevokeSessions::class)->execute($user, $request->session()->getId());
        });
        $request->session()->regenerate();
        SecurityAudit::record('account.two_factor_disabled', $request->user()->id);

        return response()->noContent()->withCookie(app(TrustedDevices::class)->forgetCookie());
    }

    public function challenge(Request $request)
    {
        abort_unless($request->hasSession(), 422, 'Vuelve a iniciar sesión desde el navegador.');
        $request->validate(['code' => ['required', 'string', 'max:40'], 'method' => ['sometimes', 'in:authenticator,email,recovery'], 'remember_device' => ['sometimes', 'boolean']]);
        $pending = $request->session()->get('two_factor_login');
        abort_unless($pending && $pending['expires'] > now()->timestamp, 422, 'El acceso ha caducado. Vuelve a introducir tu correo y contraseña.');
        $pending = array_merge(['methods' => ['authenticator'], 'verified' => [], 'email_hash' => null, 'email_expires' => null, 'email_attempts' => 0], $pending);
        $method = $request->input('method') ?: (array_values(array_diff($pending['methods'], $pending['verified']))[0] ?? 'authenticator');
        abort_unless($method === 'recovery' || (in_array($method, $pending['methods'], true) && ! in_array($method, $pending['verified'], true)), 422, 'Este método de verificación no está disponible para el acceso actual.');
        app(TwoFactor::class)->throttle($pending['user_id']);
        $user = User::findOrFail($pending['user_id']);
        abort_unless($user->two_factor_confirmed_at && hash_equals($pending['binding'], app(TwoFactor::class)->binding($user)), 422, 'Tu cuenta ha cambiado. Vuelve a iniciar sesión.');

        if ($method === 'email') {
            abort_if(($pending['email_attempts'] ?? 0) >= 5, 429, 'Has superado los intentos para este código. Vuelve a iniciar sesión para solicitar otro.');
            $pending['email_attempts'] = ($pending['email_attempts'] ?? 0) + 1;
            $expected = hash_hmac('sha256', $user->id.'|'.trim($request->input('code')), config('app.key'));
            $valid = ($pending['email_expires'] ?? 0) > now()->timestamp && is_string($pending['email_hash'] ?? null) && hash_equals($pending['email_hash'], $expected);
            $request->session()->put('two_factor_login', $pending);
            abort_unless($valid, 422, 'El código del correo no es válido o ha caducado. Solicita uno nuevo.');
            $pending['email_hash'] = null;
            $pending['verified'][] = 'email';
        } else {
            $used = DB::transaction(function () use ($request, $pending, $method) {
                $locked = User::whereKey($pending['user_id'])->lockForUpdate()->firstOrFail();
                $factor = app(TwoFactor::class);
                abort_unless($locked->two_factor_confirmed_at && hash_equals($pending['binding'], $factor->binding($locked)), 422, 'Tu cuenta ha cambiado. Vuelve a iniciar sesión.');

                return $method === 'recovery'
                    ? $factor->consumeRecovery($locked, trim($request->input('code')))
                    : $factor->consume($locked, trim($request->input('code')));
            });
            if ($method === 'recovery' || $used === 'recovery') {
                $pending['verified'] = $pending['methods'];
            } else {
                $pending['verified'][] = 'authenticator';
            }
        }

        $pending['remember_device'] = $request->boolean('remember_device', $pending['remember_device'] ?? false);
        $request->session()->put('two_factor_login', $pending);
        $remaining = array_values(array_diff($pending['methods'], $pending['verified']));
        if ($remaining) {
            return ['challenge_complete' => false, 'methods_remaining' => $remaining, 'message' => $remaining[0] === 'email' ? 'Ahora introduce el código que hemos enviado a tu correo.' : 'Ahora introduce el código de tu aplicación de autenticación.'];
        }

        return $this->completeLogin($request, $user, $pending);
    }

    public function resendSetup(Request $request): array
    {
        abort_unless($request->hasSession(), 422);
        $pending = $request->session()->get('two_factor_setup');
        abort_unless($pending && $pending['user_id'] === $request->user()->id && $pending['expires'] > now()->timestamp
            && $pending['email_required'] && hash_equals($pending['binding'], app(TwoFactor::class)->binding($request->user()->fresh())), 422, 'La configuración ha caducado. Empieza de nuevo.');
        app(TwoFactorEmailCodes::class)->issue($request->user()->fresh(), $request, 'setup');

        return ['message' => 'Te hemos enviado un código nuevo. El anterior ya no sirve.'];
    }

    public function cancelSetup(Request $request)
    {
        abort_unless($request->hasSession(), 422);
        $request->session()->forget('two_factor_setup');
        if ($request->session()->get('two_factor_security_code.purpose') === 'setup') {
            $request->session()->forget('two_factor_security_code');
        }

        return response()->noContent();
    }

    public function resend(Request $request)
    {
        abort_unless($request->hasSession(), 422, 'Vuelve a iniciar sesión desde el navegador.');
        $pending = $request->session()->get('two_factor_login');
        abort_unless($pending && $pending['expires'] > now()->timestamp && in_array('email', $pending['methods'], true) && ! in_array('email', $pending['verified'], true), 422, 'No hay un código de correo pendiente.');
        $key = 'two-factor-email:'.$pending['user_id'];
        abort_if(RateLimiter::tooManyAttempts($key, 3), 429, 'Has solicitado varios códigos. Espera unos minutos antes de pedir otro.');
        RateLimiter::hit($key, 300);
        $user = User::findOrFail($pending['user_id']);
        abort_unless(app(TwoFactor::class)->emailDeliveryAvailable(), 503, 'El envío de códigos por correo no está configurado todavía. Usa un código de recuperación o el autenticador.');
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try {
            Notification::route('mail', $user->email)->notify(new TwoFactorCodeNotification($code));
        } catch (\Throwable $exception) {
            report($exception);
            abort(503, 'No hemos podido enviar el código por correo. Inténtalo más tarde o contacta con soporte.');
        }
        $pending['email_hash'] = hash_hmac('sha256', $user->id.'|'.$code, config('app.key'));
        $pending['email_expires'] = now()->addMinutes(5)->timestamp;
        $pending['email_attempts'] = 0;
        $pending['email_delivery_failed'] = false;
        $request->session()->put('two_factor_login', $pending);

        return ['message' => 'Te hemos enviado otro código. El anterior ya no es válido.'];
    }

    public function updateMethod(Request $request)
    {
        abort_unless($request->hasSession(), 422, 'Utiliza una sesión de navegador para cambiar la verificación.');
        $data = $request->validate(['method' => ['required', 'in:authenticator,email,both'], 'current_password' => ['required', 'current_password:web'], 'code' => ['required', 'string', 'max:40']]);
        app(TwoFactor::class)->throttle($request->user()->id);
        $user = DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless(Hash::check($data['current_password'], $user->password), 422);
            abort_unless($user->two_factor_confirmed_at, 409, 'Activa primero la verificación en dos pasos.');
            if (in_array($data['method'], ['email', 'both'], true)) {
                abort_unless($user->hasVerifiedEmail(), 422, 'Confirma primero el correo de tu cuenta para poder recibir códigos.');
                abort_unless(app(TwoFactor::class)->emailDeliveryAvailable(), 422, 'El envío de correos no está configurado todavía. Elige el autenticador hasta que se configure el correo transaccional.');
            }
            abort_unless($data['method'] === 'email' || filled($user->two_factor_secret), 422, 'Configura primero la aplicación de autenticación.');
            app(TwoFactor::class)->consumeForSecurity($user, $request, 'change_method:'.$data['method'], trim($data['code']));
            $user->forceFill(['two_factor_method' => $data['method']])->save();
            app(RevokeSessions::class)->execute($user, $request->session()->getId());

            return $user;
        });
        SecurityAudit::record('account.two_factor_method_changed', $user->id);
        $request->session()->regenerate();

        return response()->json(['method' => $user->two_factor_method, 'message' => 'Método de verificación actualizado. Vuelve a autorizar tus dispositivos de confianza.'])->withCookie(app(TrustedDevices::class)->forgetCookie());
    }

    public function securityCode(Request $request): array
    {
        abort_unless($request->hasSession(), 422, 'Utiliza una sesión de navegador.');
        $data = $request->validate(['current_password' => ['required', 'current_password:web'],
            'purpose' => ['required', 'in:change_method,disable,change_email'], 'method' => ['required_if:purpose,change_method', 'in:email,authenticator,both'],
            'new_email' => ['required_if:purpose,change_email', 'email', 'max:255']]);
        $user = $request->user()->fresh();
        abort_unless($user->two_factor_confirmed_at && $user->two_factor_method === 'email', 422, 'Usa tu autenticador o un código de recuperación.');
        $purpose = match ($data['purpose']) {
            'change_method' => 'change_method:'.$data['method'],
            'change_email' => 'change_email:'.hash('sha256', mb_strtolower(trim($data['new_email']))),
            default => 'disable',
        };
        app(TwoFactorEmailCodes::class)->issue($user, $request, $purpose);

        return ['message' => 'Código enviado a tu correo actual. Caduca en 5 minutos y solo sirve para este cambio.'];
    }

    public function devices(Request $request): array
    {
        return ['devices' => TrustedDevice::where('user_id', $request->user()->id)->where('expires_at', '>', now())->orderByDesc('last_used_at')->get(['id', 'name', 'last_used_at', 'expires_at'])];
    }

    public function revokeDevice(Request $request, int $device)
    {
        TrustedDevice::where('user_id', $request->user()->id)->whereKey($device)->delete();
        SecurityAudit::record('account.trusted_device_revoked', $request->user()->id);

        return response()->noContent();
    }

    public function revokeDevices(Request $request)
    {
        TrustedDevice::where('user_id', $request->user()->id)->delete();
        SecurityAudit::record('account.trusted_devices_revoked', $request->user()->id);

        return response()->noContent()->withCookie(app(TrustedDevices::class)->forgetCookie());
    }

    private function completeLogin(Request $request, User $user, array $pending)
    {
        $user = User::findOrFail($user->id);
        abort_unless($user->two_factor_confirmed_at && hash_equals($pending['binding'], app(TwoFactor::class)->binding($user)), 422, 'La configuración de seguridad ha cambiado. Inicia sesión de nuevo.');
        $request->session()->forget('two_factor_login');
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $response = response()->json(['challenge_complete' => true, 'user' => $user, 'portfolio' => $user->portfolio()]);
        if ($pending['remember_device'] ?? false) {
            $response->withCookie(app(TrustedDevices::class)->issue($user, $request));
            SecurityAudit::record('account.trusted_device_added', $user->id);
        }

        return $response;
    }

    private function maskedEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 1).'•••@'.$domain;
    }
}
