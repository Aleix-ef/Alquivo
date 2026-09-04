<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Portfolio\Models\Portfolio;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'portfolio_name' => ['nullable', 'string', 'max:100'],
            'terms_accepted' => ['accepted'],
        ]);

        [$user, $portfolio] = DB::transaction(function () use ($data) {
            $user = User::create([...$data, 'terms_accepted_at' => now(), 'terms_version' => '2026-07']);
            $portfolio = Portfolio::create([
                'name' => $data['portfolio_name'] ?? 'Mi patrimonio',
                'currency' => 'EUR', 'country_code' => 'ES',
                'plan' => 'free', 'storage_limit_bytes' => config('plans.free.storage_limit_bytes'),
                'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(14),
            ]);
            $portfolio->members()->attach($user->id, ['role' => 'owner']);

            return [$user, $portfolio];
        });

        Auth::login($user);
        $user->sendEmailVerificationNotification();
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json(['user' => $user, 'portfolio' => $portfolio], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Las credenciales no son correctas.']]);
        }

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return ['user' => $user, 'portfolio' => $user->portfolio()];
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
        $data = $request->validate(['email' => ['required', 'email']]);
        PasswordBroker::sendResetLink($data);

        return ['message' => 'Si existe una cuenta, recibirás un enlace para restablecer la contraseña.'];
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $status = PasswordBroker::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
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

        return ['message' => 'Si el correo estaba pendiente, hemos enviado un nuevo enlace.'];
    }

    public function verifyEmail(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect(env('FRONTEND_URL', 'http://127.0.0.1:5173').'/settings?verified=1');
    }
}
