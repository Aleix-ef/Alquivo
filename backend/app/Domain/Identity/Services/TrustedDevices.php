<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

final class TrustedDevices
{
    public const COOKIE = 'alquivo_trusted_device';

    private const TTL_DAYS = 90;

    public function find(User $user, Request $request): ?TrustedDevice
    {
        $token = $request->cookie(self::COOKIE);
        if (! is_string($token) || ! preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }

        $device = TrustedDevice::where('user_id', $user->id)
            ->where('token_hash', $this->hash($token))
            ->where('expires_at', '>', now())->first();
        if ($device) {
            $device->forceFill(['last_used_at' => now()])->save();
        }

        return $device;
    }

    public function existingDeviceCookie(TrustedDevice $device, Request $request): ?Cookie
    {
        $token = $request->cookie(self::COOKIE);
        if (! is_string($token) || ! preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }

        $minutesRemaining = max(1, now()->diffInMinutes($device->expires_at, false));

        return cookie(self::COOKIE, $token, $minutesRemaining, '/', config('session.domain'), (bool) config('session.secure'), true, false, 'lax');
    }

    public function issue(User $user, Request $request): Cookie
    {
        $token = bin2hex(random_bytes(32));
        $device = TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($token),
            'name' => mb_substr((string) $request->userAgent(), 0, 240),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(self::TTL_DAYS),
        ]);
        $overflow = TrustedDevice::where('user_id', $user->id)
            ->where('id', '!=', $device->id)
            ->orderByDesc('last_used_at')->skip(9)->take(50)->pluck('id');
        if ($overflow->isNotEmpty()) {
            TrustedDevice::whereIn('id', $overflow)->delete();
        }

        return cookie(self::COOKIE, $token, 60 * 24 * self::TTL_DAYS, '/', config('session.domain'), (bool) config('session.secure'), true, false, 'lax');
    }

    public function forgetCookie(): Cookie
    {
        return cookie()->forget(self::COOKIE, '/', config('session.domain'));
    }

    public function hash(string $token): string
    {
        return hash_hmac('sha256', $token, config('app.key'));
    }
}
