<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRunStep;
use App\Domain\Assistant\Services\AIModelRouter;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AssistantReasoningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['ai.routing.chat' => 'fast', 'ai.chat_reasoning_effort' => null,
            'services.openai.key' => 'synthetic-offline-key']);
    }

    #[DataProvider('supportedEfforts')]
    public function test_effort_is_explicit_only_when_configured_and_keeps_the_production_contract(?string $effort): void
    {
        config(['ai.chat_reasoning_effort' => $effort]);
        [$user, $conversation] = $this->conversation();
        Http::fakeSequence()->push($this->tool())->push($this->reply());
        $this->actingAs($user)->postJson($this->url($conversation), ['message' => '¿Cuántos inmuebles tengo?'])
            ->assertOk()->assertJsonPath('assistant_message.content', 'Tienes un inmueble registrado.');
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertSame('gpt-6-luna', $request['model']);
            $this->assertFalse($request['store']);
            $this->assertFalse($request['parallel_tool_calls']);
            $this->assertSame(['reasoning.encrypted_content'], $request['include']);
            $this->assertTrue($request['text']['format']['strict']);
            $this->assertSame((int) config('assistant.max_output_tokens'), $request['max_output_tokens']);
            if ($effort === null) {
                $this->assertArrayNotHasKey('reasoning', $request->data());
            } else {
                $this->assertSame(['effort' => $effort], $request['reasoning']);
            }
        }
        foreach (AiRunStep::where('kind', 'provider')->get() as $step) {
            $this->assertSame($effort, $step->metadata['reasoning_effort']);
            $this->assertSame(4, $step->metadata['reasoning_tokens']);
            // Reasoning tokens are already included in output_tokens, never billed twice.
            $this->assertSame(15000, $step->estimated_cost_nano_usd);
        }
        $this->assertSame(4, config('ai.limits.max_rounds'));
    }

    public static function supportedEfforts(): array
    {
        return ['original provider default' => [null], 'candidate low' => ['low'], 'explicit baseline' => ['medium']];
    }

    #[DataProvider('invalidEfforts')]
    public function test_invalid_or_unreviewed_effort_is_rejected_before_a_provider_call(mixed $effort): void
    {
        config(['ai.chat_reasoning_effort' => $effort]);
        try {
            app(AIModelRouter::class)->route();
            $this->fail('Unreviewed reasoning must not silently change model behavior.');
        } catch (RuntimeException) {
            Http::assertNothingSent();
            $this->assertDatabaseCount('ai_run_steps', 0);
        }
    }

    public static function invalidEfforts(): array
    {
        return [['none'], ['high'], ['fast'], ['LOW'], [''], [true], [1], [['low']]];
    }

    public function test_luna_effort_is_not_applied_to_fallback_legacy_or_document_models(): void
    {
        config(['ai.chat_reasoning_effort' => 'low']);
        foreach (['complex', 'legacy', 'document', 'analysis'] as $profile) {
            $this->assertNull(app(AIModelRouter::class)->route($profile)['reasoning_effort']);
        }
        config(['ai.routing.fast' => 'gpt-6-sol']);
        $this->expectException(RuntimeException::class);
        app(AIModelRouter::class)->route('fast');
    }

    public function test_technical_fallback_keeps_sol_and_does_not_inherit_luna_low_effort(): void
    {
        config(['ai.chat_reasoning_effort' => 'low']);
        [$user, $conversation] = $this->conversation();
        Http::fakeSequence()->push(['error' => ['message' => 'synthetic']], 500)
            ->push($this->reply('gpt-6-sol', 'app_help', 'greeting'));
        $this->actingAs($user)->postJson($this->url($conversation), ['message' => 'Hola'])->assertOk();
        $requests = Http::recorded()->pluck(0)->all();
        $this->assertCount(2, $requests);
        $this->assertSame('gpt-6-luna', $requests[0]['model']);
        $this->assertSame(['effort' => 'low'], $requests[0]['reasoning']);
        $this->assertSame('gpt-6-sol', $requests[1]['model']);
        $this->assertArrayNotHasKey('reasoning', $requests[1]->data());
        $this->assertFalse(config('ai.exceptional_enabled'));
    }

    public function test_chat_payload_cannot_change_server_reasoning_configuration(): void
    {
        config(['ai.chat_reasoning_effort' => 'medium']);
        [$user, $conversation] = $this->conversation();
        Http::fakeSequence()->push($this->reply('gpt-6-luna', 'app_help', 'greeting'));
        $this->actingAs($user)->postJson($this->url($conversation), [
            'message' => 'Hola', 'reasoning' => ['effort' => 'none'], 'reasoning_effort' => 'low',
        ])->assertOk();
        Http::assertSent(fn ($request) => $request['reasoning'] === ['effort' => 'medium']);
    }

    private function conversation(): array
    {
        $user = User::factory()->create(['assistant_enabled_at' => now(),
            'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio = Portfolio::create(['name' => 'Synthetic only', 'plan' => 'founder', 'trial_ends_at' => null]);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $portfolio->properties()->create(['name' => 'Synthetic property', 'type' => 'housing', 'address_line' => 'Invented address']);

        return [$user, AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id])];
    }

    private function url(AiConversation $conversation): string
    {
        return '/api/v1/assistant/conversations/'.$conversation->id.'/messages';
    }

    private function tool(): array
    {
        return ['model' => 'gpt-6-luna', 'output' => [['type' => 'function_call', 'call_id' => 'synthetic-call',
            'name' => 'get_portfolio_overview', 'arguments' => '{}']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 10, 'output_tokens_details' => ['reasoning_tokens' => 4]]];
    }

    private function reply(string $model = 'gpt-6-luna', string $basis = 'portfolio_data', string $content = 'Tienes un inmueble registrado.'): array
    {
        return ['model' => $model, 'output' => [['type' => 'message', 'content' => [['type' => 'output_text',
            'text' => json_encode(['kind' => 'answer', 'basis' => $basis, 'content' => $content])]]]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 10, 'output_tokens_details' => ['reasoning_tokens' => 4]]];
    }
}
