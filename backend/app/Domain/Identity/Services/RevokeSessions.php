<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RevokeSessions
{
    public function execute(User $user, ?string $exceptSession = null): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();
        TrustedDevice::where('user_id', $user->id)->delete();
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table'))
                ->where('user_id', $user->id)
                ->when($exceptSession, fn ($query) => $query->where('id', '!=', $exceptSession))
                ->delete();
        }
    }
}
