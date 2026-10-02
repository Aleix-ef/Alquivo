<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Services\AssistantReply;
use App\Domain\Assistant\Services\PortfolioAssistantTools;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_conversations_are_private_to_the_authenticated_user(): void
    {
        [$owner, $portfolio] = $this->userWithPortfolio();
        [$other] = $this->userWithPortfolio();
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $owner->id]);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Mis datos']);

        $this->actingAs($other)->getJson("/api/v1/assistant/conversations/{$conversation->id}")->assertNotFound();
        $this->actingAs($other)->deleteJson("/api/v1/assistant/conversations/{$conversation->id}")->assertNotFound();
        $this->actingAs($owner)->getJson("/api/v1/assistant/conversations/{$conversation->id}")
            ->assertOk()->assertJsonPath('messages.0.content', 'Mis datos');
    }

    public function test_assistant_reports_when_openai_is_not_configured_without_saving_a_message(): void
    {
        config(['services.openai.key' => null]);
        [$user, $portfolio] = $this->userWithPortfolio();
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);

        $this->actingAs($user)->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", ['message' => 'Hola'])
            ->assertStatus(503)->assertJsonPath('message', 'El asistente todavía no está configurado.');
        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_assistant_uses_read_only_tools_and_saves_the_answer(): void
    {
        config(['services.openai.key' => 'test-key', 'assistant.model' => 'test-model']);
        [$user, $portfolio] = $this->userWithPortfolio();
        $property = $portfolio->properties()->create([
            'name' => 'Piso Centro', 'type' => 'housing', 'address_line' => 'Calle Mayor 1', 'current_value' => 180000,
        ]);
        Transaction::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'direction' => 'income',
            'category' => 'rent', 'description' => 'Alquiler', 'amount' => 900,
            'transaction_date' => today(), 'status' => 'paid',
        ]);
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);

        Http::fakeSequence()
            ->push([
                'model' => 'test-model',
                'output' => [[
                    'type' => 'function_call', 'call_id' => 'call_1', 'name' => 'get_portfolio_overview',
                    'arguments' => '{}', 'status' => 'completed',
                ]],
                'usage' => ['input_tokens' => 30, 'output_tokens' => 10],
            ])
            ->push([
                'model' => 'test-model',
                'output' => [[
                    'type' => 'message', 'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => json_encode(['kind' => 'answer', 'basis' => 'portfolio_data', 'content' => 'Tu cartera vale 180.000 €.'])]],
                ]],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 15],
            ]);

        $this->actingAs($user)->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", [
            'message' => '¿Cuánto vale mi cartera?',
        ])->assertOk()
            ->assertJsonPath('assistant_message.content', 'Tu cartera vale 180.000 €.')
            ->assertJsonPath('assistant_message.metadata.kind', 'answer')
            ->assertJsonPath('assistant_message.input_tokens', 80)
            ->assertJsonPath('usage.used', 1)
            ->assertJsonPath('usage.tokens.input.used', 80)
            ->assertJsonPath('usage.tokens.output.used', 25);

        $this->assertDatabaseHas('ai_messages', ['conversation_id' => $conversation->id, 'role' => 'assistant']);
        Http::assertSent(fn ($request) => $request['store'] === false && $request['safety_identifier'] !== null);
        Http::assertSent(fn ($request) => $request['text']['format']['strict'] === true && $request['text']['format']['type'] === 'json_schema');
        Http::assertSent(fn ($request) => collect($request['input'])->contains(fn ($item) => ($item['type'] ?? null) === 'function_call_output' && str_contains($item['output'], '180000')));
    }

    public function test_paid_movement_tool_cannot_support_a_false_no_pending_rent_claim(): void
    {
        config(['services.openai.key' => 'test-key', 'assistant.model' => 'test-model']);
        [$user, $portfolio] = $this->userWithPortfolio();
        $property = $portfolio->properties()->create(['name' => 'Centro', 'type' => 'housing', 'address_line' => 'Ficticia']);
        $lease = $property->leases()->create(['portfolio_id' => $portfolio->id, 'status' => 'active',
            'start_date' => '2025-01-01', 'monthly_rent' => '550.00']);
        $lease->charges()->create(['portfolio_id' => $portfolio->id, 'period' => today()->format('Y-m'),
            'due_date' => today(), 'amount' => '550.00', 'status' => 'pending']);
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        Http::fakeSequence()
            ->push(['model' => 'test-model', 'output' => [[
                'type' => 'function_call', 'call_id' => 'financial_1', 'name' => 'get_financial_summary',
                'arguments' => json_encode(['from' => null, 'to' => null, 'property_id' => null]),
            ]], 'usage' => ['input_tokens' => 30, 'output_tokens' => 10]])
            ->push(['model' => 'test-model', 'output' => [[
                'type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                    'kind' => 'answer', 'basis' => 'portfolio_data',
                    'content' => 'No hay ingresos ni gastos pendientes registrados.',
                ])]],
            ]], 'usage' => ['input_tokens' => 40, 'output_tokens' => 15]]);
        $response = $this->actingAs($user)->postJson('/api/v1/assistant/conversations/'.$conversation->id.'/messages',
            ['message' => '¿Qué tal voy de pasta este mes?'])->assertOk();
        $content = $response->json('assistant_message.content');
        $this->assertStringContainsString('550,00 EUR pendientes', $content);
        $this->assertStringNotContainsString('No hay ingresos ni gastos pendientes registrados', $content);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_monthly_limit_is_enforced_before_calling_openai(): void
    {
        config(['services.openai.key' => 'test-key', 'assistant.limits.founder' => 0]);
        [$user, $portfolio] = $this->userWithPortfolio();
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        Http::fake();

        $this->actingAs($user)->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", ['message' => 'Hola'])
            ->assertUnprocessable()->assertJsonValidationErrors('message');
        Http::assertNothingSent();
    }

    public function test_monthly_token_budget_is_enforced_before_calling_openai(): void
    {
        config(['services.openai.key' => 'test-key', 'assistant.token_limits.founder.input' => 100]);
        [$user, $portfolio] = $this->userWithPortfolio();
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        DB::table('ai_monthly_usage')->insert([
            'portfolio_id' => $portfolio->id,
            'user_id' => $user->id,
            'month' => now()->startOfMonth()->toDateString(),
            'requests' => 1,
            'input_tokens' => 100,
            'output_tokens' => 0,
        ]);
        Http::fake();

        $this->actingAs($user)->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", ['message' => 'Hola'])
            ->assertUnprocessable()->assertJsonValidationErrors('message');
        Http::assertNothingSent();
    }

    public function test_scope_fallback_is_saved_and_restored_without_provider_free_text(): void
    {
        config(['services.openai.key' => 'test-key']);
        [$user, $portfolio] = $this->userWithPortfolio();
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        Http::fake(['*' => Http::response(['output' => [[
            'type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'kind' => 'out_of_scope', 'basis' => 'none', 'content' => 'Texto ajeno que no debe mostrarse.',
            ])]],
        ]]])]);

        $this->actingAs($user)->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", ['message' => 'Dame una receta de cocina'])
            ->assertOk()->assertJsonPath('assistant_message.content', AssistantReply::FALLBACKS['out_of_scope'])
            ->assertJsonPath('assistant_message.metadata.kind', 'out_of_scope');
        $this->getJson("/api/v1/assistant/conversations/{$conversation->id}")->assertOk()
            ->assertJsonPath('messages.1.metadata.kind', 'out_of_scope');
        Http::assertSentCount(1);
    }

    public function test_empty_financial_records_and_missing_valuations_are_explicit(): void
    {
        [, $portfolio] = $this->userWithPortfolio();
        $portfolio->properties()->create(['name' => 'Sin valorar', 'type' => 'housing', 'address_line' => 'Calle 1']);
        $tools = app(PortfolioAssistantTools::class);
        $overview = $tools->execute($portfolio, 'get_portfolio_overview', []);
        $this->assertSame(1, $overview['properties_without_valuation']);
        $this->assertSame(0, $overview['current_month']['recorded_transaction_count']);
        $this->assertNull($tools->execute($portfolio, 'list_properties', [])['properties'][0]['current_value']);
        $this->assertSame(0, $tools->execute($portfolio, 'get_financial_summary', [])['recorded_transaction_count']);
    }

    public function test_malformed_provider_reply_is_not_persisted_as_an_answer(): void
    {
        config(['services.openai.key' => 'test-key']);
        [$user, $portfolio] = $this->userWithPortfolio();
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        Http::fake(['*' => Http::response(['output' => [[
            'type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Respuesta sin validar']],
        ]]])]);
        $this->actingAs($user)->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", ['message' => '¿Cómo va mi cartera?'])
            ->assertStatus(502)->assertJsonPath('usage.used', 1);
        $this->assertSame(0, $conversation->messages()->where('role', 'assistant')->count());
    }

    public function test_property_context_is_authorized_before_spending_quota(): void
    {
        config(['services.openai.key' => 'test-key']);
        [$user, $portfolio] = $this->userWithPortfolio();
        [, $other] = $this->userWithPortfolio();
        $property = $other->properties()->create(['name' => 'Ajeno', 'type' => 'housing', 'address_line' => 'Secreto']);
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        Http::fake();
        $this->actingAs($user)->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", ['message' => 'Resume este inmueble', 'property_id' => $property->id])->assertNotFound();
        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_messages', 0);
        $this->assertDatabaseCount('ai_monthly_usage', 0);
    }

    public function test_owned_property_context_and_server_derived_sources_are_returned(): void
    {
        config(['services.openai.key' => 'test-key']);
        [$user, $portfolio] = $this->userWithPortfolio();
        $property = $portfolio->properties()->create(['name' => 'Centro', 'type' => 'housing', 'address_line' => 'Secreto']);
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        Http::fakeSequence()->push(['output' => [[
            'type' => 'function_call', 'call_id' => 'call_1', 'name' => 'get_property_details', 'arguments' => json_encode(['property_id' => $property->id]),
        ]]])->push(['output' => [[
            'type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['kind' => 'answer', 'basis' => 'portfolio_data', 'content' => 'No has registrado una valoración.'])]],
        ]]]);
        $this->actingAs($user)->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", ['message' => 'Resume este inmueble', 'property_id' => $property->id])
            ->assertOk()->assertJsonPath('assistant_message.metadata.sources.0.path', '/properties/'.$property->id)
            ->assertJsonStructure(['assistant_message' => ['metadata' => ['checked_at']]]);
        Http::assertSent(fn ($request) => str_contains($request['instructions'], "inmueble con ID {$property->id}"));
        Http::assertNotSent(fn ($request) => str_contains(json_encode($request->data()), 'Secreto'));
    }

    public function test_incomplete_and_invalid_responses_still_record_token_consumption(): void
    {
        config(['services.openai.key' => 'test-key']);
        [$user, $portfolio] = $this->userWithPortfolio();
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        Http::fakeSequence()
            ->push(['status' => 'incomplete', 'usage' => ['input_tokens' => 50, 'output_tokens' => 10]])
            ->push(['output' => [], 'usage' => ['input_tokens' => 80, 'output_tokens' => 20]]);
        foreach ([50, 130] as $expected) {
            $this->actingAs($user)->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", ['message' => 'Resume mi cartera'])
                ->assertStatus(502)->assertJsonPath('usage.tokens.input.used', $expected);
        }
        $this->assertDatabaseHas('ai_monthly_usage', ['user_id' => $user->id, 'requests' => 2, 'input_tokens' => 130, 'output_tokens' => 30]);
        $this->assertSame(0, $conversation->messages()->where('role', 'assistant')->count());
    }

    public function test_budget_is_rechecked_after_a_tool_round_and_output_limit_is_capped(): void
    {
        config(['services.openai.key' => 'test-key', 'assistant.token_limits.founder.input' => 50, 'assistant.token_limits.founder.output' => 200]);
        [$user, $portfolio] = $this->userWithPortfolio();
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        Http::fakeSequence()->push(['output' => [[
            'type' => 'function_call', 'call_id' => 'call_1', 'name' => 'get_portfolio_overview', 'arguments' => '{}',
        ]], 'usage' => ['input_tokens' => 50, 'output_tokens' => 10]]);
        $this->actingAs($user)->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", ['message' => 'Resume mi cartera'])
            ->assertStatus(502)->assertJsonPath('usage.remaining', 0)->assertJsonPath('usage.tokens.input.used', 50);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['max_output_tokens'] === 200);
    }

    public function test_support_is_authenticated_and_does_not_invent_a_contact_address(): void
    {
        $this->getJson('/api/v1/support')->assertUnauthorized();
        [$user] = $this->userWithPortfolio();
        $user->forceFill(['email_verified_at' => null])->save();
        config(['support.email' => '']);
        $this->actingAs($user)->getJson('/api/v1/support')->assertOk()->assertJsonPath('email', null);
        config(['support.email' => 'bad\r\naddress']);
        $this->getJson('/api/v1/support')->assertOk()->assertJsonPath('email', null);
        config(['support.email' => 'help@example.com']);
        $this->getJson('/api/v1/support')->assertOk()->assertJsonPath('email', 'help@example.com');
    }

    private function userWithPortfolio(): array
    {
        $user = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio = Portfolio::create(['name' => 'Cartera', 'plan' => 'founder', 'trial_ends_at' => null]);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        return [$user, $portfolio];
    }
}
