<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\TrustedDevice;
use App\Domain\Identity\Services\RevokeSessions;
use App\Domain\Identity\Services\TrustedDevices;
use App\Domain\Identity\Services\TwoFactor;
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
        return ['enabled' => (bool) $request->user()->two_factor_confirmed_at, 'method' => $request->user()->two_factor_method ?: 'authenticator', 'email_verified' => $request->user()->hasVerifiedEmail(), 'email_delivery_available' => app(TwoFactor::class)->emailDeliveryAvailable(), 'email_hint' => $this->maskedEmail($request->user()->email), 'recovery_codes_remaining' => count($request->user()->two_factor_recovery_codes ?? [])];
    }

    public function setup(Request $request): array
    {
        abort_unless($request->hasSession(), 422, 'Utiliza la sesión de navegador para configurar el doble factor.');
        $request->validate(['current_password' => ['required', 'current_password:web']]);
        abort_if($request->user()->two_factor_confirmed_at, 409, 'El doble factor ya está activo.');
        $secret = (new Google2FA)->generateSecretKey();
        $request->session()->put('two_factor_setup', ['secret' => $secret, 'user_id' => $request->user()->id, 'password' => hash('sha256', $request->user()->getAuthPassword()), 'expires' => now()->addMinutes(10)->timestamp]);

        // Never send this secret to an external QR service.
        return ['secret' => $secret];
    }

    public function confirm(Request $request): array
    {
        abort_unless($request->hasSession(), 422, 'Utiliza la sesión de navegador para confirmar el doble factor.');
        $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/D']]);
        $pending = $request->session()->get('two_factor_setup');
        abort_unless($pending && $pending['expires'] > now()->timestamp && $pending['user_id'] === $request->user()->id, 422, 'La configuración ha caducado. Empieza de nuevo.');
        app(TwoFactor::class)->throttle($request->user()->id);
        $codes = DB::transaction(function () use ($request, $pending) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($pending['password'], hash('sha256', $user->getAuthPassword())), 422, 'Vuelve a confirmar tu contraseña.');
            abort_if($user->two_factor_confirmed_at, 409, 'El doble factor ya está activo.');
            $user->two_factor_secret = $pending['secret'];
            app(TwoFactor::class)->consume($user, $request->string('code')->toString());
            $codes = array_map(fn () => bin2hex(random_bytes(10)), range(1, 8));
            $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => array_map(fn ($code) => hash('sha256', $code), $codes)])->save();
            app(RevokeSessions::class)->execute($user, $request->session()->getId());

            return $codes;
        });
        $request->session()->forget('two_factor_setup');
        $request->session()->regenerate();
        SecurityAudit::record('account.two_factor_enabled', $request->user()->id);

        return ['recovery_codes' => $codes];
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
            app(TwoFactor::class)->consume($user, trim($request->input('code')));
            $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null])->save();
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
            $used = DB::transaction(function () use ($request, $pending) {
                $locked = User::whereKey($pending['user_id'])->lockForUpdate()->firstOrFail();
                $factor = app(TwoFactor::class);
                abort_unless($locked->two_factor_confirmed_at && hash_equals($pending['binding'], $factor->binding($locked)), 422, 'Tu cuenta ha cambiado. Vuelve a iniciar sesión.');

                return $factor->consume($locked, trim($request->input('code')));
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
            abort_unless($user->two_factor_confirmed_at, 409, 'Activa primero la verificación en dos pasos.');
            if (in_array($data['method'], ['email', 'both'], true)) {
                abort_unless($user->hasVerifiedEmail(), 422, 'Confirma primero el correo de tu cuenta para poder recibir códigos.');
                abort_unless(app(TwoFactor::class)->emailDeliveryAvailable(), 422, 'El envío de correos no está configurado todavía. Elige el autenticador hasta que se configure el correo transaccional.');
            }
            app(TwoFactor::class)->consume($user, trim($data['code']));
            $user->forceFill(['two_factor_method' => $data['method']])->save();
            TrustedDevice::where('user_id', $user->id)->delete();

            return $user;
        });
        SecurityAudit::record('account.two_factor_method_changed', $user->id);

        return response()->json(['method' => $user->two_factor_method, 'message' => 'Método de verificación actualizado. Vuelve a autorizar tus dispositivos de confianza.'])->withCookie(app(TrustedDevices::class)->forgetCookie());
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
        $response = response()->json(['user' => $user, 'portfolio' => $user->portfolio()]);
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
