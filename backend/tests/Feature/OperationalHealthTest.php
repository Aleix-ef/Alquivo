<?php

namespace Tests\Feature;

use App\Console\Commands\OperationalHealth;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
    }

    private function snapshot(): array
    {
        $this->assertSame(0, Artisan::call('ops:health'));

        return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_private_probe_reads_only_aggregate_queue_metrics_and_ignores_delayed_jobs(): void
    {
        config(['queue.default' => 'database', 'cache.default' => 'database']);
        Cache::put(OperationalHealth::SCHEDULER_KEY, now()->timestamp, 600);
        foreach ([-400, -30, 600] as $offset) {
            DB::table('jobs')->insert(['queue' => 'default', 'payload' => 'PRIVATE_SYNTHETIC_JOB_PAYLOAD',
                'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->timestamp + $offset, 'created_at' => now()->timestamp - 900]);
        }
        foreach ([now()->subMinutes(5), now()->subHours(2)] as $date) {
            DB::table('failed_jobs')->insert(['uuid' => fake()->uuid(), 'connection' => 'database', 'queue' => 'default',
                'payload' => 'PRIVATE_SYNTHETIC_JOB_PAYLOAD', 'exception' => 'PRIVATE_SYNTHETIC_EXCEPTION', 'failed_at' => $date]);
        }
        $result = $this->snapshot();
        $this->assertTrue($result['database_ok']);
        $this->assertTrue($result['queue_supported']);
        $this->assertSame(2, $result['queued_ready']);
        $this->assertSame(400, $result['oldest_ready_age_seconds']);
        $this->assertSame(1, $result['failed_last_hour']);
        $this->assertSame(now()->timestamp, $result['scheduler_last_tick']);
        $this->assertStringNotContainsString('PRIVATE_SYNTHETIC', json_encode($result));
        $this->assertDatabaseCount('jobs', 3);
        $this->assertDatabaseCount('failed_jobs', 2);
    }

    public function test_empty_queue_is_zero_and_missing_heartbeat_is_not_fabricated(): void
    {
        config(['queue.default' => 'database', 'cache.default' => 'database']);
        $result = $this->snapshot();
        $this->assertTrue($result['database_ok']);
        $this->assertSame(0, $result['queued_ready']);
        $this->assertSame(0, $result['oldest_ready_age_seconds']);
        $this->assertSame(0, $result['failed_last_hour']);
        $this->assertNull($result['scheduler_last_tick']);
    }

    public function test_broken_schema_returns_controlled_unhealthy_json_without_exception_details(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.table' => 'missing_synthetic_table']);
        $result = $this->snapshot();
        $this->assertFalse($result['database_ok']);
        $this->assertNull($result['queued_ready']);
        $this->assertStringNotContainsString('missing_synthetic_table', json_encode($result));
        $this->assertStringNotContainsString('SQLSTATE', json_encode($result));
    }

    public function test_unsupported_queue_and_unshared_cache_are_explicit(): void
    {
        config(['queue.default' => 'sync', 'cache.default' => 'array']);
        $result = $this->snapshot();
        $this->assertTrue($result['database_ok']);
        $this->assertFalse($result['queue_supported']);
        $this->assertFalse($result['scheduler_shared_cache']);
        $this->assertNull($result['queued_ready']);
        $this->assertNull($result['scheduler_last_tick']);
    }

    public function test_scheduler_event_records_real_execution_and_expires(): void
    {
        config(['cache.default' => 'database']);
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'ops:scheduler-heartbeat');
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $event->run($this->app);
        $this->assertSame(now()->timestamp, Cache::get(OperationalHealth::SCHEDULER_KEY));
        $this->travel(11)->minutes();
        $this->assertNull(Cache::get(OperationalHealth::SCHEDULER_KEY));
    }

    public function test_invalid_or_future_heartbeat_cannot_claim_a_healthy_scheduler(): void
    {
        config(['queue.default' => 'database', 'cache.default' => 'database']);
        foreach (['PRIVATE_SYNTHETIC_CONTENT', ['timestamp' => now()->timestamp], now()->timestamp + 600] as $invalid) {
            Cache::put(OperationalHealth::SCHEDULER_KEY, $invalid, 600);
            $this->assertNull($this->snapshot()['scheduler_last_tick']);
        }
    }
}
