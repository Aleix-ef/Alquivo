<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class SecurityAudit
{
    public static function record(string $event, ?int $userId = null): void
    {
        Log::channel('security')->info($event, [
            'user_id' => $userId,
            'source' => hash_hmac('sha256', (string) request()->ip(), (string) config('app.key')),
        ]);
    }
}
