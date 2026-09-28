<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssistantExpenseFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai.key' => 'synthetic-key', 'assistant.enabled' => true, 'beta.assistant_validated' => true,
            'ai.actions.enabled' => true, 'assistant.model' => 'gpt-5.4-mini', 'ai.fallback_profile' => null]);
        Http::preventStrayRequests();
    }

    public function test_natural_expense_is_reviewed_edited_and_executed_only_once(): void
    {
        [$user, $portfolio, $conversation, $property] = $this->fixture();
        $this->fakeExpense($property->id);
        $payload = ['message' => 'Registra 84 € de fontanería en San Nicolás.', 'client_request_id' => (string) Str::uuid()];
        $response = $this->actingAs($user)->postJson($this->url($conversation), $payload)->assertOk();
        $response->assertJsonPath('assistant_message.metadata.kind', 'action_proposal')
            ->assertJsonPath('assistant_message.metadata.proposals.0.preview.amount', '84.00')
            ->assertJsonPath('assistant_message.metadata.proposals.0.preview.status', 'pending');
        $this->assertDatabaseCount('transactions', 0);
        $id = $response->json('assistant_message.metadata.proposals.0.id');
        $this->postJson("/api/v1/assistant/proposals/{$id}/revise", ['revision' => 1, 'amount' => '85.25', 'status' => 'paid'])
            ->assertOk()->assertJsonPath('proposal.revision', 2);
        $this->postJson("/api/v1/assistant/proposals/{$id}/confirm", ['revision' => 1])->assertConflict();
        $this->assertDatabaseCount('transactions', 0);
        $first = $this->postJson("/api/v1/assistant/proposals/{$id}/confirm", ['revision' => 2])->assertOk();
        $this->postJson("/api/v1/assistant/proposals/{$id}/confirm", ['revision' => 2])->assertOk()
            ->assertJsonPath('proposal.result.transaction_id', $first->json('proposal.result.transaction_id'));
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', ['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'amount' => '85.25', 'direction' => 'expense']);
        $this->getJson('/api/v1/transactions')->assertOk()->assertJsonPath('data.0.amount', '85.25');
        $run = AiRun::findOrFail($response->json('run.id'));
        $this->assertSame(40, $run->input_tokens);
        $this->assertGreaterThan(0, $run->estimated_cost_nano_usd);
        $this->assertSame(1, $run->steps()->where('kind', 'action')->where('status', 'executed')->count());
        Http::assertSentCount(2); // confirming/editing never consults the model
    }

    public function test_lost_response_replays_same_messages_proposal_and_quota(): void
    {
        [$user, , $conversation, $property] = $this->fixture();
        $this->fakeExpense($property->id);
        $payload = ['message' => 'Registra 84 € de fontanería en San Nicolás.', 'client_request_id' => (string) Str::uuid()];
        $first = $this->actingAs($user)->postJson($this->url($conversation), $payload)->assertOk();
        $this->postJson($this->url($conversation), $payload)->assertOk()->assertJsonPath('run.id', $first->json('run.id'))
            ->assertJsonPath('assistant_message.id', $first->json('assistant_message.id'))->assertJsonPath('usage.used', 1);
        $this->getJson('/api/v1/assistant/runs/'.$first->json('run.id'))->assertOk()->assertJsonPath('assistant_message.id', $first->json('assistant_message.id'));
        $this->assertDatabaseCount('ai_action_proposals', 1);
        $this->assertDatabaseCount('ai_messages', 2);
        $this->postJson($this->url($conversation), [...$payload, 'message' => 'otro gasto'])->assertConflict();
        Http::assertSentCount(2);
    }

    public function test_ambiguous_property_asks_without_preparing_or_writing(): void
    {
        [$user, $portfolio, $conversation, $property] = $this->fixture();
        $property->update(['name' => 'San Nicolás primero']);
        $portfolio->properties()->create(['name' => 'San Nicolás segundo', 'type' => 'housing', 'address_line' => 'Private fixture']);
        Http::fakeSequence()->push($this->tool('search_properties', ['query' => 'San Nicolas']));
        $this->actingAs($user)->postJson($this->url($conversation), ['message' => 'Registra 84 € en San Nicolás'])
            ->assertOk()->assertJsonPath('assistant_message.metadata.kind', 'clarification');
        $this->assertDatabaseCount('ai_action_proposals', 0);
        $this->assertDatabaseCount('transactions', 0);
        Http::assertSentCount(1);
    }

    public function test_proposal_tool_cannot_invent_an_id_without_resolving_it(): void
    {
        [$user, , $conversation, $property] = $this->fixture();
        Http::fakeSequence()->push($this->tool('propose_expense', $this->expense($property->id)))->push($this->reply());
        $this->actingAs($user)->postJson($this->url($conversation), ['message' => 'Gasto de 84 €'])->assertOk();
        $this->assertDatabaseCount('ai_action_proposals', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('ai_run_steps', ['tool' => 'propose_expense', 'status' => 'rejected']);
    }

    public function test_identical_names_can_be_resolved_from_an_authorized_property_page(): void
    {
        [$user, $portfolio, $conversation, $property] = $this->fixture();
        $portfolio->properties()->create(['name' => 'San Nicolás', 'type' => 'housing', 'address_line' => 'Another synthetic property']);
        $this->fakeExpense($property->id);
        $this->actingAs($user)->postJson($this->url($conversation), ['message' => 'Registra 84 € aquí', 'property_id' => $property->id])
            ->assertOk()->assertJsonPath('assistant_message.metadata.proposals.0.preview.property.id', $property->id);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_identical_names_show_safe_links_and_city_for_clarification(): void
    {
        [$user, $portfolio, $conversation, $property] = $this->fixture();
        $portfolio->properties()->create(['name' => 'San Nicolás', 'city' => 'Valencia', 'type' => 'housing', 'address_line' => 'Private address']);
        Http::fakeSequence()->push($this->tool('search_properties', ['query' => 'San Nicolás']));
        $this->actingAs($user)->postJson($this->url($conversation), ['message' => 'Registra un gasto de 84 € en San Nicolás'])
            ->assertOk()->assertJsonPath('assistant_message.metadata.kind', 'clarification')
            ->assertJsonCount(2, 'assistant_message.metadata.sources');
        $this->assertDatabaseCount('ai_action_proposals', 0);
    }

    public function test_invalid_arguments_fail_closed_and_model_has_no_confirmation_tool(): void
    {
        [$user, , $conversation] = $this->fixture();
        $bad = $this->tool('get_portfolio_overview', []);
        $bad['output'][0]['arguments'] = 'not-json';
        Http::fakeSequence()->push($bad)->push($this->tool('confirm_action', ['confirmed' => true]))->push($this->reply());
        $this->actingAs($user)->postJson($this->url($conversation), ['message' => 'sí, confirma'])->assertOk();
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('ai_run_steps', ['tool' => 'get_portfolio_overview', 'status' => 'rejected']);
        $this->assertDatabaseHas('ai_run_steps', ['tool' => 'unregistered', 'status' => 'rejected']);
        Http::assertSent(fn ($request) => ! collect($request['tools'])->contains(fn ($tool) => str_contains($tool['name'], 'confirm')));
    }

    public function test_another_owner_cannot_read_a_run_or_its_proposal(): void
    {
        [$user, , $conversation, $property] = $this->fixture();
        $this->fakeExpense($property->id);
        $result = $this->actingAs($user)->postJson($this->url($conversation), ['message' => 'Registra 84 €'])->assertOk();
        [$other] = $this->fixture();
        $id = $result->json('assistant_message.metadata.proposals.0.id');
        $this->actingAs($other)->getJson('/api/v1/assistant/runs/'.$result->json('run.id'))->assertNotFound();
        $this->getJson("/api/v1/assistant/proposals/{$id}")->assertNotFound();
        $this->postJson("/api/v1/assistant/proposals/{$id}/confirm", ['revision' => 1])->assertNotFound();
    }

    public function test_disabling_deletes_sensitive_drafts_not_metrics_or_confirmed_expenses(): void
    {
        [$user, , $conversation, $property] = $this->fixture();
        $this->fakeExpense($property->id);
        $result = $this->actingAs($user)->postJson($this->url($conversation), ['message' => 'Registra 84 €'])->assertOk();
        $id = $result->json('assistant_message.metadata.proposals.0.id');
        $this->postJson("/api/v1/assistant/proposals/{$id}/confirm", ['revision' => 1])->assertOk();
        $this->deleteJson('/api/v1/assistant/activation')->assertNoContent();
        $this->assertDatabaseCount('ai_action_proposals', 0);
        $this->assertDatabaseCount('ai_messages', 0);
        $this->assertDatabaseCount('ai_runs', 1);
        $this->assertDatabaseCount('transactions', 1);
        $this->getJson("/api/v1/assistant/proposals/{$id}")->assertNotFound();
    }

    public function test_configured_fallback_records_both_calls(): void
    {
        [$user, , $conversation] = $this->fixture();
        config(['ai.fallback_profile' => 'complex']);
        Http::fakeSequence()->push(['output' => [], 'usage' => ['input_tokens' => 10, 'output_tokens' => 3]])->push($this->reply());
        $result = $this->actingAs($user)->postJson($this->url($conversation), ['message' => 'Hola'])->assertOk();
        $run = AiRun::findOrFail($result->json('run.id'));
        $this->assertSame(2, $run->steps()->where('kind', 'provider')->count());
        $this->assertSame(30, $run->input_tokens);
        $this->assertSame(1, $result->json('usage.used'));
        Http::assertSent(fn ($request) => $request['model'] === 'gpt-6-sol');
    }

    public function test_failed_request_replay_does_not_spend_again(): void
    {
        [$user, , $conversation] = $this->fixture();
        Http::fakeSequence()->push(['error' => ['message' => 'private provider error']], 500);
        $payload = ['message' => 'Hola', 'client_request_id' => (string) Str::uuid()];
        $first = $this->actingAs($user)->postJson($this->url($conversation), $payload)->assertStatus(502);
        $this->postJson($this->url($conversation), $payload)->assertStatus(502)->assertJsonPath('run.id', $first->json('run.id'))->assertJsonPath('usage.used', 1);
        Http::assertSentCount(1);
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio = Portfolio::create(['name' => 'Synthetic', 'currency' => 'EUR', 'plan' => 'founder', 'trial_ends_at' => null]);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'San Nicolás', 'type' => 'housing', 'address_line' => 'Never send this address']);
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);

        return [$user, $portfolio, $conversation, $property];
    }

    private function fakeExpense(int $propertyId): void
    {
        Http::fakeSequence()->push($this->tool('search_properties', ['query' => 'San Nicolás']))->push($this->tool('propose_expense', $this->expense($propertyId)));
    }

    private function expense(int $propertyId): array
    {
        return ['property_id' => $propertyId, 'amount' => '84.00', 'category' => 'maintenance', 'description' => 'Fontanería', 'transaction_date' => today()->toDateString(), 'status' => 'pending'];
    }

    private function tool(string $name, array $arguments): array
    {
        return ['output' => [['type' => 'function_call', 'call_id' => (string) Str::uuid(), 'name' => $name, 'arguments' => json_encode((object) $arguments)]], 'usage' => ['input_tokens' => 20, 'output_tokens' => 5]];
    }

    private function reply(): array
    {
        return ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['kind' => 'answer', 'basis' => 'app_help', 'content' => 'greeting'])]]]], 'usage' => ['input_tokens' => 20, 'output_tokens' => 5]];
    }

    private function url(AiConversation $conversation): string
    {
        return '/api/v1/assistant/conversations/'.$conversation->id.'/messages';
    }
}
