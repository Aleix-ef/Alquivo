<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Models\AiRunStep;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssistantLatencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Http::preventStrayRequests();
    }

    private function makeRun(): AiRun
    {
        $portfolio = Portfolio::create(['name' => 'PRIVATE-PORTFOLIO-NAME']);
        $user = User::factory()->create(['email' => 'private-person@example.test']);

        return AiRun::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id,
            'client_request_id' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'synthetic'),
            'plan' => 'beta', 'routing_version' => 'test', 'billing_month' => today()->startOfMonth()]);
    }

    private function step(AiRun $run, array $data = []): AiRunStep
    {
        return $run->steps()->create([...['kind' => 'provider', 'model' => 'gpt-6-luna', 'status' => 'completed',
            'latency_ms' => 100, 'input_tokens' => 100, 'cached_input_tokens' => 50,
            'provider_request_id' => 'DO-NOT-PRINT-PROVIDER-ID', 'metadata' => ['note' => 'DO-NOT-PRINT-CONTENT']], ...$data]);
    }

    public function test_report_uses_existing_metrics_without_private_content_network_or_writes(): void
    {
        $run = $this->makeRun();
        foreach ([100, 200, 400] as $ms) {
            $this->step($run, ['latency_ms' => $ms]);
        }
        $this->step($run, ['kind' => 'tool', 'tool' => 'list_rent_charges', 'latency_ms' => null, 'status' => 'started']);
        $before = AiRunStep::count();
        DB::enableQueryLog();
        try {
            $this->assertSame(0, Artisan::call('assistant:latency', ['--json' => true]));
            $output = Artisan::output();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $report = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame(4, $report['matched_steps']);
        $this->assertFalse($report['partial']);
        $provider = collect($report['groups'])->firstWhere('kind', 'provider');
        $this->assertEquals(200, $provider['median_ms']);
        $this->assertSame(400, $provider['p95_ms']);
        $this->assertSame(400, $provider['max_ms']);
        $this->assertEquals(50, $provider['cached_input_percent']);
        $tool = collect($report['groups'])->firstWhere('kind', 'tool');
        $this->assertSame(1, $tool['unknown_latency']);
        $this->assertNull($tool['median_ms']);
        $this->assertNull($tool['cached_input_percent']);
        foreach (['PRIVATE-PORTFOLIO', 'private-person', 'DO-NOT-PRINT', $run->id] as $private) {
            $this->assertStringNotContainsString($private, $output);
        }
        $sql = strtolower(implode(' ', array_column($queries, 'query')));
        $this->assertStringNotContainsString('ai_messages', $sql);
        $this->assertStringNotContainsString('metadata', $sql);
        $this->assertStringNotContainsString('insert ', $sql);
        $this->assertSame($before, AiRunStep::count());
        Http::assertNothingSent();
    }

    public function test_partial_report_keeps_window_failures_and_missing_latencies_explicit(): void
    {
        $run = $this->makeRun();
        $this->step($run, ['created_at' => now()->subDays(8)]);
        $this->step($run, ['latency_ms' => 100]);
        $this->step($run, ['latency_ms' => 900, 'status' => 'failed']);
        $this->step($run, ['latency_ms' => null, 'status' => 'started']);
        $this->assertSame(0, Artisan::call('assistant:latency', ['--json' => true, '--limit' => 2]));
        $report = json_decode(Artisan::output(), true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame(3, $report['matched_steps']);
        $this->assertSame(2, $report['sampled_steps']);
        $this->assertTrue($report['partial']);
        $this->assertSame(1, $report['groups'][0]['failed_or_rejected']);
        $this->assertSame(1, $report['groups'][0]['unknown_latency']);
        $this->assertEquals(900, $report['groups'][0]['median_ms']);
    }

    public function test_empty_report_and_invalid_options_do_not_require_provider_configuration(): void
    {
        config(['services.openai.key' => null, 'assistant.enabled' => false]);
        $this->assertSame(0, Artisan::call('assistant:latency', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame([], $report['groups']);
        $this->assertSame(0, $report['matched_steps']);
        foreach ([['--days' => 0], ['--days' => 31], ['--days' => '1.5'], ['--limit' => 0], ['--limit' => 5001]] as $options) {
            $this->assertSame(1, Artisan::call('assistant:latency', $options));
        }
        Http::assertNothingSent();
    }

    public function test_console_output_sanitizes_labels_and_does_not_turn_null_time_into_zero(): void
    {
        $run = $this->makeRun();
        $this->step($run, ['model' => "private-person@example.test\nINJECTED", 'latency_ms' => null]);
        $this->assertSame(0, Artisan::call('assistant:latency'));
        $output = Artisan::output();
        $this->assertStringContainsString('unregistered', $output);
        $this->assertStringContainsString('—', $output);
        $this->assertStringNotContainsString('private-person', $output);
        $this->assertStringNotContainsString('INJECTED', $output);
        Http::assertNothingSent();
    }
}
