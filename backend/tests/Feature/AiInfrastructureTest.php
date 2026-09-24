<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Providers\FakeAIProvider;
use App\Domain\Assistant\Providers\OpenAIProvider;
use App\Domain\Assistant\Providers\ProviderException;
use App\Domain\Assistant\Services\AICostCalculator;
use App\Domain\Assistant\Services\AIModelRouter;
use App\Domain\Assistant\Services\AiRunLedger;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AiInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_router_preserves_legacy_and_requires_explicit_exceptional_enablement(): void
    {
        config(['assistant.model' => 'gpt-5.4-mini', 'ai.routing.chat' => 'legacy']);
        $router = app(AIModelRouter::class);
        $this->assertSame('gpt-5.4-mini', $router->route()['model']);
        $this->assertSame('gpt-5.6-luna', $router->route('fast')['model']);
        $this->expectException(RuntimeException::class);
        $router->route('exceptional');
    }

    public function test_costs_use_integer_units_and_do_not_double_count_cached_or_reasoning_tokens(): void
    {
        $result = app(AICostCalculator::class)->calculate('gpt-5.6-luna', [
            'input_tokens' => 1000,
            'output_tokens' => 100,
            'input_tokens_details' => ['cached_tokens' => 200, 'cache_write_tokens' => 100],
            'output_tokens_details' => ['reasoning_tokens' => 50],
        ]);
        $this->assertSame(700 * 200 + 200 * 20 + 100 * 250 + 100 * 1200, $result['estimated_cost_nano_usd']);
        $this->assertSame('0.000289000', $result['estimated_cost_usd']);
        $this->assertNull(app(AICostCalculator::class)->calculate('unpriced-synthetic-model', ['input_tokens' => 20, 'output_tokens' => 5])['estimated_cost_nano_usd']);
    }

    public function test_settlement_is_idempotent_and_uses_requested_model_for_snapshot_alias(): void
    {
        [$user, $portfolio, $run, $step] = $this->reservedCall();
        $ledger = app(AiRunLedger::class);
        $response = $this->response(['model' => 'gpt-5.6-luna-2099-01-01']);
        $ledger->completeCall($step, $response, 25, $portfolio, $user);
        $ledger->completeCall($step, $response, 25, $portfolio, $user);
        $run->refresh();
        $this->assertSame(100, $run->input_tokens);
        $this->assertSame(10, $run->output_tokens);
        $this->assertSame(32000, $run->estimated_cost_nano_usd);
        $this->assertSame(0, $run->reserved_cost_nano_usd);
        $this->assertFalse($run->cost_incomplete);
        $this->assertSame('gpt-5.6-luna-2099-01-01', $step->fresh()->metadata['reported_model']);
        $this->assertSame(100, DB::table('ai_monthly_usage')->where('portfolio_id', $portfolio->id)->value('input_tokens'));
    }

    public function test_unknown_usage_keeps_reservation_and_does_not_claim_free_consumption(): void
    {
        [$user, $portfolio, $run, $step] = $this->reservedCall();
        $reserved = $run->fresh()->reserved_cost_nano_usd;
        $response = $this->response();
        unset($response['usage']);
        app(AiRunLedger::class)->completeCall($step, $response, 25, $portfolio, $user);
        $this->assertSame($reserved, $run->fresh()->reserved_cost_nano_usd);
        $this->assertTrue($run->fresh()->cost_incomplete);
        $this->assertNull($step->fresh()->estimated_cost_nano_usd);
    }

    public function test_malformed_usage_keeps_reservation_instead_of_settling_as_zero(): void
    {
        foreach ([['input_tokens' => 'unknown', 'output_tokens' => 10], ['input_tokens' => -5, 'output_tokens' => 10], ['input_tokens' => 5.5, 'output_tokens' => 10]] as $usage) {
            [$user, $portfolio, $run, $step] = $this->reservedCall();
            $reserved = $run->fresh()->reserved_cost_nano_usd;
            app(AiRunLedger::class)->completeCall($step, $this->response(['usage' => $usage]), 25, $portfolio, $user);
            $this->assertSame($reserved, $run->fresh()->reserved_cost_nano_usd);
            $this->assertTrue($run->fresh()->cost_incomplete);
            $this->assertNull($step->fresh()->estimated_cost_nano_usd);
        }
    }

    public function test_settlement_uses_the_price_snapshot_reserved_before_a_config_change(): void
    {
        [$user, $portfolio, $run, $step] = $this->reservedCall();
        $models = config('ai.models');
        $models['gpt-5.6-luna']['input'] = 20000;
        $models['gpt-5.6-luna']['output'] = 120000;
        config(['ai.models' => $models, 'ai.pricing_version' => 'a-later-price-version']);
        app(AiRunLedger::class)->completeCall($step, $this->response(), 25, $portfolio, $user);
        $this->assertSame(32000, $run->fresh()->estimated_cost_nano_usd);
        $this->assertNotSame('a-later-price-version', $step->fresh()->pricing_version);
    }

    public function test_late_failure_does_not_overwrite_a_settled_call(): void
    {
        [$user, $portfolio, $run, $step] = $this->reservedCall();
        $ledger = app(AiRunLedger::class);
        $ledger->completeCall($step, $this->response(), 25, $portfolio, $user);
        $ledger->failCall($step, 'late_connection_error', 30);
        $this->assertSame('completed', $step->fresh()->status);
        $this->assertFalse($run->fresh()->cost_incomplete);
    }

    public function test_failed_call_retains_reservation_for_unknown_billing(): void
    {
        [, , $run, $step] = $this->reservedCall();
        $reserved = $run->fresh()->reserved_cost_nano_usd;
        app(AiRunLedger::class)->failCall($step, 'connection_error', 20);
        $this->assertSame($reserved, $run->fresh()->reserved_cost_nano_usd);
        $this->assertTrue($run->fresh()->cost_incomplete);
        $this->assertSame('failed', $step->fresh()->status);
    }

    public function test_portfolio_monthly_budget_includes_previously_reserved_calls(): void
    {
        [, $portfolio, $run] = $this->reservedCall();
        $reserved = $run->fresh()->reserved_cost_nano_usd;
        config(['ai.limits.portfolio_monthly_cost_nano_usd' => $reserved + 1]);
        $another = $this->runFor($portfolio, $run->user_id);
        $this->expectException(RuntimeException::class);
        app(AiRunLedger::class)->reserveCall($another, app(AIModelRouter::class)->route('fast'), $this->request());
    }

    public function test_run_budget_rejects_before_creating_a_provider_step(): void
    {
        [, , $run] = $this->reservedCall();
        $before = $run->steps()->count();
        config(['ai.limits.run_cost_nano_usd' => 1]);
        try {
            app(AiRunLedger::class)->reserveCall($run, app(AIModelRouter::class)->route('fast'), $this->request());
            $this->fail('The run budget must reject this reservation.');
        } catch (RuntimeException) {
            $this->assertSame($before, $run->steps()->count());
        }
    }

    public function test_provider_forces_store_false_and_sanitizes_error_bodies(): void
    {
        config(['services.openai.key' => 'synthetic-test-key']);
        Http::fakeSequence()->push(['output' => [], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]])
            ->push(['error' => ['message' => 'SENSITIVE_SYNTHETIC_BODY']], 500);
        $provider = new OpenAIProvider;
        $provider->generate(['model' => 'gpt-5.6-luna', 'store' => true], 1.0);
        Http::assertSent(fn ($request) => $request['store'] === false);
        try {
            $provider->generate(['model' => 'gpt-5.6-luna'], 1.0);
            $this->fail('Expected a sanitized provider error.');
        } catch (ProviderException $exception) {
            $this->assertSame('http_500', $exception->errorCode);
            $this->assertTrue($exception->retryable);
            $this->assertStringNotContainsString('SENSITIVE_SYNTHETIC_BODY', $exception->getMessage());
        }
    }

    public function test_deleting_an_account_does_not_reset_the_global_provider_budget(): void
    {
        [$user, , $run] = $this->reservedCall();
        $reserve = $run->fresh()->reserved_cost_nano_usd;
        $user->delete();
        $this->assertSame($reserve, DB::table('ai_provider_usage')->value('reserved_cost_nano_usd'));
        [, $portfolio, $another] = $this->reservedCall();
        $used = DB::table('ai_provider_usage')->value('reserved_cost_nano_usd');
        config(['ai.limits.global_monthly_cost_nano_usd' => $used]);
        $this->expectException(RuntimeException::class);
        app(AiRunLedger::class)->reserveCall($another, app(AIModelRouter::class)->route('fast'), $this->request());
    }

    public function test_fake_provider_records_requests_and_never_falls_through_to_network(): void
    {
        Http::preventStrayRequests();
        $provider = new FakeAIProvider;
        $provider->push($this->response());
        $this->assertSame('completed', $provider->generate(['model' => 'synthetic'], 1.0)['status']);
        $this->assertSame([['model' => 'synthetic']], $provider->requests);
        $this->expectException(RuntimeException::class);
        $provider->generate([], 1.0);
    }

    private function reservedCall(): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Synthetic portfolio', 'currency' => 'EUR', 'country_code' => 'ES', 'plan' => 'founder']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $run = $this->runFor($portfolio, $user->id);
        $step = app(AiRunLedger::class)->reserveCall($run, app(AIModelRouter::class)->route('fast'), $this->request());

        return [$user, $portfolio, $run, $step];
    }

    private function runFor(Portfolio $portfolio, int $userId): AiRun
    {
        return AiRun::create([
            'portfolio_id' => $portfolio->id, 'user_id' => $userId,
            'client_request_id' => (string) Str::uuid(), 'request_hash' => hash('sha256', Str::random()),
            'plan' => 'founder', 'routing_version' => 'test-only', 'billing_month' => now()->startOfMonth()->toDateString(),
        ]);
    }

    private function request(): array
    {
        return ['model' => 'gpt-5.6-luna', 'input' => [['role' => 'user', 'content' => 'Synthetic only']], 'max_output_tokens' => 500];
    }

    private function response(array $overrides = []): array
    {
        return [...[
            'id' => 'synthetic_response', 'status' => 'completed', 'model' => 'gpt-5.6-luna', 'output' => [],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 10],
        ], ...$overrides];
    }
}
