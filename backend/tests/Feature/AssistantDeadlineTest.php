<?php

namespace Tests\Feature;

use App\Domain\Assistant\Contracts\AIProviderInterface;
use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRunStep;
use App\Domain\Assistant\Providers\ProviderException;
use App\Domain\Assistant\Services\AssistantClock;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private AssistantClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.openai.key' => 'synthetic-offline-only', 'assistant.enabled' => true, 'beta.assistant_validated' => true,
            'assistant.timeout_seconds' => 45, 'ai.fallback_profile' => 'complex']);
        $this->clock = new class extends AssistantClock
        {
            public float $time = 0;

            public function seconds(): float
            {
                return $this->time;
            }
        };
        $this->app->instance(AssistantClock::class, $this->clock);
    }

    public function test_late_provider_result_is_accounted_but_cannot_execute_a_tool_or_fallback(): void
    {
        $provider = $this->provider(function () {
            $this->clock->time = 46;

            return ['model' => 'gpt-6-luna', 'usage' => ['input_tokens' => 100, 'output_tokens' => 10],
                'output' => [['type' => 'function_call', 'call_id' => 'late', 'name' => 'list_contacts',
                    'arguments' => '{"query":null,"property_id":null,"page":1,"response_mode":"list"}']]];
        });
        $this->send()->assertStatus(502);
        $this->assertCount(1, $provider->timeouts);
        $this->assertSame(45.0, $provider->timeouts[0]);
        $step = AiRunStep::where('kind', 'provider')->sole();
        $this->assertSame('deadline_exceeded', $step->error_code);
        $this->assertSame(100, $step->input_tokens);
        $this->assertSame(15000, $step->estimated_cost_nano_usd);
        $this->assertSame(0, AiRunStep::where('kind', 'tool')->count());
        $this->assertDatabaseCount('ai_action_proposals', 0);
        Http::assertNothingSent();
    }

    public function test_technical_fallback_gets_only_remaining_time_not_a_fresh_deadline(): void
    {
        $provider = $this->provider(function ($request) {
            if ($request['model'] === 'gpt-6-luna') {
                $this->clock->time = 12;
                throw new ProviderException('connection_error', true);
            }
            $this->clock->time = 15;

            return $this->reply();
        });
        $this->send()->assertOk();
        $this->assertSame([45.0, 33.0], $provider->timeouts);
        $this->assertSame(['gpt-6-luna', 'gpt-6-sol'], AiRunStep::where('kind', 'provider')->orderBy('id')->pluck('model')->all());
        $this->assertSame(4, config('ai.limits.max_rounds'));
        Http::assertNothingSent();
    }

    public function test_no_fallback_starts_after_the_shared_deadline(): void
    {
        $provider = $this->provider(function () {
            $this->clock->time = 45;
            throw new ProviderException('connection_error', true);
        });
        $this->send()->assertStatus(502);
        $this->assertCount(1, $provider->timeouts);
        $this->assertSame(1, AiRunStep::where('kind', 'provider')->count());
        $this->assertSame(0, AiRunStep::where('kind', 'tool')->count());
        Http::assertNothingSent();
    }

    public function test_full_creation_catalog_cannot_bypass_the_run_cost_cap_for_a_technical_fallback(): void
    {
        // Reproduce the live evaluation's unavailable fallback without making a paid request.
        config(['ai.actions.creation_enabled' => true, 'ai.limits.run_cost_nano_usd' => 100000000]);
        $provider = $this->provider(function ($request) {
            if ($request['model'] === 'gpt-6-luna') {
                $this->clock->time = 5;
                throw new ProviderException('connection_error', true);
            }

            return $this->reply();
        });
        $this->send()->assertStatus(502);
        $this->assertCount(1, $provider->timeouts);
        $step = AiRunStep::where('kind', 'provider')->sole();
        $this->assertSame('connection_error', $step->error_code);
        // Current Sol input/output/cache-write tariffs are exactly 20 times Luna's.
        $this->assertGreaterThan(config('ai.limits.run_cost_nano_usd'), $step->reserved_cost_nano_usd * 21);
        $this->assertDatabaseCount('ai_action_proposals', 0);
        Http::assertNothingSent();
    }

    public function test_business_date_changes_do_not_change_elapsed_time_budget(): void
    {
        $provider = $this->provider(function () {
            $this->travel(1)->days();
            $this->clock->time = 2;

            return $this->reply();
        });
        try {
            $this->send()->assertOk();
            $this->assertSame([45.0], $provider->timeouts);
        } finally {
            $this->travelBack();
        }
    }

    private function provider(\Closure $respond): AIProviderInterface
    {
        $provider = new class($respond) implements AIProviderInterface
        {
            public array $timeouts = [];

            public function __construct(private \Closure $respond) {}

            public function generate(array $request, float $timeout): array
            {
                $this->timeouts[] = $timeout;

                return ($this->respond)($request);
            }
        };
        $this->app->instance(AIProviderInterface::class, $provider);

        return $provider;
    }

    private function send()
    {
        $user = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio = Portfolio::create(['name' => 'Synthetic deadline', 'plan' => 'founder', 'trial_ends_at' => null]);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);

        return $this->actingAs($user)->postJson('/api/v1/assistant/conversations/'.$conversation->id.'/messages', ['message' => 'Hola']);
    }

    private function reply(): array
    {
        return ['output' => [['type' => 'message', 'content' => [['type' => 'output_text',
            'text' => '{"kind":"answer","basis":"app_help","content":"greeting"}']]]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 10]];
    }
}
