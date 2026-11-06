<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Administration\Services\AdminAccess;
use App\Domain\Identity\Services\TwoFactor;
use App\Domain\Identity\Services\TwoFactorEmailCodes;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class AdminAccessController extends Controller
{
    public function status(Request $request, AdminAccess $access): array
    {
        return [
            'authorized' => true, 'verified' => $access->verified($request),
            'method' => $request->user()->two_factor_method ?: 'authenticator',
            'email_available' => app(TwoFactor::class)->emailDeliveryAvailable(),
            'expires_at' => $access->verified($request) ? $request->session()->get(AdminAccess::SESSION_KEY)['expires_at'] : null,
            'verification_minutes' => (int) config('admin.verification_minutes'),
        ];
    }

    public function code(Request $request): array
    {
        abort_unless(in_array($request->user()->two_factor_method, ['email', 'both'], true), 422, 'Utiliza tu aplicación de autenticación.');
        app(TwoFactorEmailCodes::class)->issue($request->user(), $request, 'business_admin_access');

        return ['message' => 'Código enviado al correo verificado de tu cuenta.'];
    }

    public function verify(Request $request, AdminAccess $access): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'email_code' => ['nullable', 'string', 'regex:/^\d{6}$/D'],
            'recovery' => ['sometimes', 'boolean'],
        ]);
        app(TwoFactor::class)->throttle($request->user()->id);
        $user = DB::transaction(function () use ($request, $data, $access) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($access->allowed($user), 403);
            $factor = app(TwoFactor::class);
            if ($request->boolean('recovery')) {
                $factor->consumeRecovery($user, trim($data['code']));
            } elseif ($user->two_factor_method === 'email') {
                app(TwoFactorEmailCodes::class)->consume($user, $request, 'business_admin_access', trim($data['code']));
            } else {
                abort_unless($factor->consume($user, trim($data['code'])) === 'authenticator', 422, 'Utiliza un código de la aplicación.');
                if ($user->two_factor_method === 'both') {
                    app(TwoFactorEmailCodes::class)->consume($user, $request, 'business_admin_access', $data['email_code'] ?? '');
                }
            }

            return $user;
        });
        $access->remember($request, $user);
        SecurityAudit::record('admin.access_verified', $user->id);

        return ['verified' => true];
    }

    public function lock(Request $request): Response
    {
        $request->session()->forget(AdminAccess::SESSION_KEY);

        return response()->noContent();
    }
}
