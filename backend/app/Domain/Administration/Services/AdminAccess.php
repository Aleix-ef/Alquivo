<?php

namespace App\Domain\Administration\Services;

use App\Domain\Identity\Services\TwoFactor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\TransientToken;

final class AdminAccess
{
    public const SESSION_KEY = 'business_admin_verification';

    public function grant(?User $user): ?object
    {
        if (! $user || ! $user->hasVerifiedEmail() || ! $user->two_factor_confirmed_at
            || mb_strtolower($user->email) !== config('admin.authorized_email')) {
            return null;
        }
        $token = $user->currentAccessToken();
        if (($token && ! $token instanceof TransientToken) || ! Schema::hasTable('business_admins')) {
            return null;
        }

        return DB::table('business_admins')->where('user_id', $user->id)
            ->where('email', mb_strtolower($user->email))->first();
    }

    public function allowed(?User $user): bool
    {
        return $this->grant($user) !== null;
    }

    public function verified(Request $request): bool
    {
        $grant = $this->grant($request->user());
        $proof = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return $grant && is_array($proof)
            && ($proof['user_id'] ?? null) === $request->user()->id
            && ($proof['grant_version'] ?? null) === $grant->grant_version
            && is_int($proof['expires_at'] ?? null) && $proof['expires_at'] > now()->timestamp
            && is_string($proof['binding'] ?? null)
            && hash_equals(app(TwoFactor::class)->binding($request->user()), $proof['binding']);
    }

    public function remember(Request $request, User $user): void
    {
        $grant = $this->grant($user);
        abort_unless($grant && $request->hasSession(), 403);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id, 'grant_version' => $grant->grant_version,
            'binding' => app(TwoFactor::class)->binding($user),
            'expires_at' => now()->addMinutes((int) config('admin.verification_minutes'))->timestamp,
        ]);
    }
}
