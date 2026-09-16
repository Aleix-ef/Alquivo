<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\RevokeSessions;
use App\Domain\Identity\Services\TwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function status(Request $request): array
    {
        return ['enabled' => (bool) $request->user()->two_factor_confirmed_at, 'recovery_codes_remaining' => count($request->user()->two_factor_recovery_codes ?? [])];
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
            app(RevokeSessions::class)->execute($user, $request->session()->getId());
        });
        $request->session()->regenerate();
        SecurityAudit::record('account.two_factor_disabled', $request->user()->id);

        return response()->noContent();
    }

    public function challenge(Request $request): array
    {
        abort_unless($request->hasSession(), 422, 'Vuelve a iniciar sesión desde el navegador.');
        $request->validate(['code' => ['required', 'string', 'max:40']]);
        $pending = $request->session()->get('two_factor_login');
        abort_unless($pending && $pending['expires'] > now()->timestamp, 422, 'El acceso ha caducado. Vuelve a introducir tu correo y contraseña.');
        app(TwoFactor::class)->throttle($pending['user_id']);
        $user = DB::transaction(function () use ($request, $pending) {
            $user = User::whereKey($pending['user_id'])->lockForUpdate()->firstOrFail();
            $factor = app(TwoFactor::class);
            abort_unless($user->two_factor_confirmed_at && hash_equals($pending['binding'], $factor->binding($user)), 422, 'Tu cuenta ha cambiado. Vuelve a iniciar sesión.');
            $factor->consume($user, trim($request->input('code')));

            return $user;
        });
        $request->session()->forget('two_factor_login');
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return ['user' => $user, 'portfolio' => $user->portfolio()];
    }
}
