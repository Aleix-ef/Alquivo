<?php

namespace App\Console\Commands;

use App\Support\ProductionReadiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Private CLI probe: aggregate operational facts only, never payloads or exception text. */
class OperationalHealth extends Command
{
    public const SCHEDULER_KEY = 'ops:scheduler-last-tick:v1';

    protected $signature = 'ops:health';

    protected $description = 'Emitir métricas operativas agregadas para el monitor privado del VPS';

    public function handle(): int
    {
        $snapshot = [
            'version' => 1,
            'checked_at' => now()->timestamp,
            'database_ok' => false,
            'queue_supported' => config('queue.default') === 'database',
            'queued_ready' => null,
            'oldest_ready_age_seconds' => null,
            'failed_last_hour' => null,
            'scheduler_last_tick' => null,
            'scheduler_shared_cache' => config('cache.default') === 'database',
            'production_ready' => false,
        ];

        try {
            DB::select('SELECT 1');
            $snapshot['database_ok'] = true;
            if ($snapshot['queue_supported']) {
                $ready = DB::table(config('queue.connections.database.table', 'jobs'))->where('available_at', '<=', now()->timestamp);
                $snapshot['queued_ready'] = (clone $ready)->count();
                $oldest = (clone $ready)->min('available_at');
                $snapshot['oldest_ready_age_seconds'] = $oldest === null ? 0 : max(0, now()->timestamp - (int) $oldest);
                $snapshot['failed_last_hour'] = DB::table(config('queue.failed.table', 'failed_jobs'))
                    ->where('failed_at', '>=', now()->subHour())->count();
            }
            if ($snapshot['scheduler_shared_cache']) {
                $tick = Cache::get(self::SCHEDULER_KEY);
                $snapshot['scheduler_last_tick'] = is_int($tick) && $tick > 0 && $tick <= now()->timestamp + 60 ? $tick : null;
            }
        } catch (Throwable) {
            // A schema/query failure is unhealthy too. Never serialize SQL, bindings or credentials.
            $snapshot['database_ok'] = false;
        }
        try {
            $snapshot['production_ready'] = app(ProductionReadiness::class)->failures() === [];
        } catch (Throwable) {
            // Only a boolean crosses this boundary, not configuration or exception details.
        }

        $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
