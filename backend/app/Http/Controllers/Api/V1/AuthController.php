<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\LegalEvidence;
use App\Domain\Identity\Services\RevokeSessions;
use App\Domain\Identity\Services\TrustedDevices;
use App\Domain\Identity\Services\TwoFactor;
use App\Domain\Portfolio\Models\Portfolio;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Support\SecurityAudit;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'portfolio_name' => ['nullable', 'string', 'max:100'],
            'terms_accepted' => ['accepted'],
            'terms_version' => ['required', Rule::in([config('legal.terms_version')])],
        ], [
            'terms_version.required' => 'Actualiza la página y revisa las condiciones antes de crear tu cuenta.',
            'terms_version.in' => 'Las condiciones han cambiado. Actualiza la página y revísalas antes de crear tu cuenta.',
        ]);

        [$user, $portfolio] = DB::transaction(function () use ($data) {
            $user = User::create([...$data, 'terms_accepted_at' => now(), 'terms_version' => config('legal.terms_version')]);
            app(LegalEvidence::class)->accept($user, 'terms', config('legal.terms_version'));
            $portfolio = Portfolio::create([
                'name' => $data['portfolio_name'] ?? 'Mi patrimonio',
                'currency' => 'EUR', 'country_code' => 'ES',
                'plan' => 'free', 'storage_limit_bytes' => config('plans.free.storage_limit_bytes'),
                'subscription_status' => 'free',
                'trial_ends_at' => null,
            ]);
            $portfolio->members()->attach($user->id, ['role' => 'owner']);

            return [$user, $portfolio];
        });

        Auth::guard('web')->login($user);
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            report($exception);
        }
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json(['user' => $user, 'portfolio' => $portfolio], 201);
    }

    public function login(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if ($request->hasSession()) {
            $request->session()->forget('two_factor_login');
        }
        $guard = Auth::guard('web');
        if (! $guard->validate($data)) {
            SecurityAudit::record('auth.failed', null);
            throw ValidationException::withMessages(['email' => ['Las credenciales no son correctas.']]);
        }

        $user = $guard->getProvider()->retrieveByCredentials($data);
        $guard->getProvider()->rehashPasswordIfRequired($user, $data);
        if ($user->two_factor_confirmed_at) {
            abort_unless($request->hasSession(), 422, 'Este acceso necesita una sesión de navegador.');
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $trusted = app(TrustedDevices::class);
            if ($device = $trusted->find($user, $request)) {
                $guard->login($user);
                $request->session()->regenerate();

                $response = response()->json(['user' => $user, 'portfolio' => $user->portfolio(), 'trusted_device' => true]);
                if ($cookie = $trusted->existingDeviceCookie($device, $request)) {
                    $response->withCookie($cookie);
                }

                return $response;
            }

            $methods = match ($user->two_factor_method ?: 'authenticator') {
                'email' => ['email'],
                'both' => ['authenticator', 'email'],
                default => ['authenticator'],
            };
            $pending = ['user_id' => $user->id, 'binding' => app(TwoFactor::class)->binding($user), 'expires' => now()->addMinutes(5)->timestamp, 'methods' => $methods, 'verified' => [], 'email_hash' => null, 'email_expires' => null, 'email_attempts' => 0];
            if (in_array('email', $methods, true)) {
                $pending = $this->sendTwoFactorEmail($user, $pending);
            }
            $request->session()->put('two_factor_login', $pending);

            return ['two_factor_required' => true, 'methods' => $methods, 'email_hint' => $this->maskedEmail($user->email), 'email_delivery_failed' => (bool) ($pending['email_delivery_failed'] ?? false)];
        }
        $guard->login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return ['user' => $user, 'portfolio' => $user->portfolio()];
    }

    private function sendTwoFactorEmail(User $user, array $pending): array
    {
        abort_unless($user->hasVerifiedEmail(), 422, 'Confirma el correo de tu cuenta antes de usarlo como segundo factor.');
        if (! app(TwoFactor::class)->emailDeliveryAvailable()) {
            $pending['email_delivery_failed'] = true;

            return $pending;
        }
        $key = 'two-factor-email:'.$user->id;
        abort_if(RateLimiter::tooManyAttempts($key, 3), 429, 'Has solicitado varios códigos. Espera unos minutos antes de volver a intentarlo.');
        RateLimiter::hit($key, 300);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try {
            Notification::route('mail', $user->email)->notify(new TwoFactorCodeNotification($code));
        } catch (\Throwable $exception) {
            report($exception);
            $pending['email_delivery_failed'] = true;

            return $pending;
        }
        $pending['email_hash'] = hash_hmac('sha256', $user->id.'|'.$code, config('app.key'));
        $pending['email_expires'] = now()->addMinutes(5)->timestamp;
        $pending['email_attempts'] = 0;
        $pending['email_delivery_failed'] = false;

        return $pending;
    }

    private function maskedEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 1).'•••@'.$domain;
    }

    public function me(Request $request)
    {
        return ['user' => $request->user(), 'portfolio' => $request->user()->portfolio()];
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    public function forgotPassword(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email']]);
        PasswordBroker::sendResetLink($data);

        return ['message' => 'Si existe una cuenta, recibirás un enlace para restablecer la contraseña.'];
    }

    public function resetPassword(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $status = PasswordBroker::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            app(RevokeSessions::class)->execute($user);
            SecurityAudit::record('account.password_reset', $user->id);
        });
        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => ['El enlace no es válido o ha caducado.']]);
        }

        return ['message' => 'Contraseña actualizada.'];
    }

    public function resendVerification(Request $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return ['message' => config('mail.default') === 'log'
            ? 'En este entorno local el correo no se envía. Se registra en los logs de la aplicación.'
            : 'Si el correo estaba pendiente, hemos enviado un nuevo enlace.'];
    }

    public function verifyEmail(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return ['verified' => true];
    }
}
