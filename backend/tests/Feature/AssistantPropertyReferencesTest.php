<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiActionProposal;
use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Services\ToolRegistry;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssistantPropertyReferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
        config(['ai.resolve_property_references' => true, 'services.openai.key' => 'synthetic-offline-key']);
        Http::preventStrayRequests();
    }

    #[DataProvider('humanNames')]
    public function test_composed_queries_reuse_exact_resolution_balances_and_unique_charge_evidence(string $query): void
    {
        $f = $this->fixture();
        $registry = app(ToolRegistry::class);
        $result = $registry->execute($f['portfolio'], $f['user'], $f['run'], 'list_rent_charges', $this->arguments($query));
        $this->assertSame(1, $result['count']);
        $this->assertSame($f['september']->id, $result['charges'][0]['id']);
        $this->assertSame('550.00', $result['summary']['remaining_amount']);
        $this->assertSame([$f['september']->id], $f['run']->steps()->where('kind', 'resolution')
            ->where('tool', 'list_rent_charges')->latest('id')->first()->metadata['rent_charge_ids']);
        $proposal = $registry->execute($f['portfolio'], $f['user'], $f['run'], 'propose_rent_payment', [
            'rent_charge_id' => $f['september']->id, 'amount' => '300.00', 'transaction_date' => '2026-09-20', 'payment_method' => null,
        ]);
        $this->assertSame('300.00', $proposal['proposal']['preview']['amount']);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame('0.00', $f['september']->fresh()->paid_amount);
        Http::assertNothingSent();
    }

    public static function humanNames(): array
    {
        return [['San Nicolás de Valencia'], ['San Nicolas Valencia'], ['San Nicolás en Valencia']];
    }

    public function test_financial_query_reuses_all_time_source_without_a_model_search_round(): void
    {
        $f = $this->fixture();
        $result = app(ToolRegistry::class)->execute($f['portfolio'], $f['user'], $f['run'], 'get_financial_summary', [
            'property_query' => 'San Nicolás de Valencia', 'property_id' => null, 'time_scope' => 'all_time', 'from' => null, 'to' => null,
        ]);
        $this->assertSame('all_time', $result['time_scope']);
        $this->assertSame(300.0, $result['income']);
        $this->assertSame(300.0, $result['net']);
        $this->assertSame('800.00', $result['rent_charges_pending_all_periods']['remaining_amount']);
        $this->assertDatabaseCount('ai_action_proposals', 0);
        Http::assertNothingSent();
    }

    public function test_city_from_history_cannot_disambiguate_an_explicit_homonym_in_the_current_message(): void
    {
        $f = $this->fixture();
        $conversation = AiConversation::findOrFail($f['run']->conversation_id);
        $conversation->messages()->create(['role' => 'user', 'content' => '¿Qué vale San Nicolás de Valencia?']);
        $message = $conversation->messages()->create(['role' => 'user', 'content' => 'Apunta 84 del fontanero en San Nicolás']);
        $f['run']->update(['user_message_id' => $message->id]);
        $registry = app(ToolRegistry::class);
        // Intentionally simulate the model adding the city not present in this request.
        $r = $registry->execute($f['portfolio'], $f['user'], $f['run'], 'search_properties', ['query' => 'San Nicolás Valencia']);
        $this->assertTrue($r['needs_clarification']);
        $this->assertSame(2, $r['count']);
        $this->assertDatabaseCount('ai_action_proposals', 0);
        foreach (['San Nicolás Valencia', 'el de Valencia'] as $explicit) {
            $message->update(['content' => $explicit]);
            $r = $registry->execute($f['portfolio'], $f['user'], $f['run'], 'search_properties', ['query' => 'San Nicolás Valencia']);
            $this->assertFalse($r['needs_clarification']);
        }
        $message->update(['content' => 'Apunta 84 en San Nicolás']);
        $r = $registry->execute($f['portfolio'], $f['user'], $f['run'], 'search_properties', ['query' => 'San Nicolás Valencia'], $f['property']->id);
        $this->assertFalse($r['needs_clarification']); // The authorized HTTP property context still works.
        foreach (['Apunta un cobro de San Nicolás', 'Apunta un cobro de San Nicolás Madrid'] as $ambiguousOrConflicting) {
            $message->update(['content' => $ambiguousOrConflicting]);
            $r = $registry->execute($f['portfolio'], $f['user'], $f['run'], 'list_rent_charges', [
                'property_id' => $f['property']->id, 'property_query' => null,
                'lease_id' => null, 'period' => '2026-09', 'status' => 'pending',
            ]);
            $this->assertTrue($r['needs_clarification']);
            $this->assertSame([], $f['run']->steps()->where('kind', 'resolution')->where('tool', 'list_rent_charges')->latest('id')->first()->metadata['rent_charge_ids']);
        }
        Http::assertNothingSent();
    }

    public function test_ambiguous_reference_clears_previous_charge_evidence_and_never_prepares_a_payment(): void
    {
        $f = $this->fixture();
        $registry = app(ToolRegistry::class);
        $registry->execute($f['portfolio'], $f['user'], $f['run'], 'list_rent_charges', $this->arguments('San Nicolás Valencia'));
        $result = $registry->execute($f['portfolio'], $f['user'], $f['run'], 'list_rent_charges', $this->arguments('San Nicolás'));
        $this->assertTrue($result['needs_clarification']);
        $this->assertSame('property', $result['clarification_domain']);
        $this->assertSame(2, $result['count']);
        $this->assertSame([], $f['run']->steps()->where('kind', 'resolution')->where('tool', 'list_rent_charges')
            ->latest('id')->first()->metadata['rent_charge_ids']);
        $this->expectException(InvalidArgumentException::class);
        $registry->execute($f['portfolio'], $f['user'], $f['run'], 'propose_rent_payment', [
            'rent_charge_id' => $f['september']->id, 'amount' => '300.00', 'transaction_date' => '2026-09-20', 'payment_method' => null,
        ]);
    }

    public function test_reference_is_not_fuzzy_and_does_not_search_other_portfolios(): void
    {
        $f = $this->fixture();
        $foreign = Portfolio::create(['name' => 'Foreign synthetic portfolio']);
        $foreign->properties()->create(['name' => 'Casa Externa', 'type' => 'housing', 'address_line' => 'Private synthetic address']);
        foreach (['Casa Externa', 'San Nico Valencia'] as $query) {
            $result = app(ToolRegistry::class)->execute($f['portfolio'], $f['user'], $f['run'], 'get_financial_summary', [
                'property_query' => $query, 'property_id' => null, 'time_scope' => 'all_time', 'from' => null, 'to' => null,
            ]);
            $this->assertTrue($result['needs_clarification']);
            $this->assertSame(0, $result['count']);
            $this->assertSame([], $result['properties']);
            $this->assertArrayNotHasKey('income', $result);
            $this->assertStringNotContainsString('Private synthetic address', json_encode($result));
        }
    }

    public function test_conflicting_id_and_name_are_rejected_instead_of_choosing_a_target(): void
    {
        $f = $this->fixture();
        $args = $this->arguments('San Nicolás Valencia');
        $args['property_id'] = $f['madrid']->id;
        $this->expectException(InvalidArgumentException::class);
        app(ToolRegistry::class)->execute($f['portfolio'], $f['user'], $f['run'], 'list_rent_charges', $args);
    }

    public function test_missing_period_does_not_turn_multiple_charges_into_unique_write_evidence(): void
    {
        $f = $this->fixture();
        $args = $this->arguments('San Nicolás Valencia');
        $args['period'] = null;
        $result = app(ToolRegistry::class)->execute($f['portfolio'], $f['user'], $f['run'], 'list_rent_charges', $args);
        $this->assertSame(2, $result['count']);
        $this->assertSame('800.00', $result['summary']['remaining_amount']);
        $this->assertSame([], $f['run']->steps()->where('kind', 'resolution')->where('tool', 'list_rent_charges')
            ->latest('id')->first()->metadata['rent_charge_ids']);
        $this->assertDatabaseCount('ai_action_proposals', 0);
    }

    public function test_closed_schemas_add_no_operation_and_can_restore_original_catalog_for_comparison(): void
    {
        $f = $this->fixture();
        $registry = app(ToolRegistry::class);
        $candidate = $registry->definitions($f['portfolio'], $f['user']);
        config(['ai.resolve_property_references' => false]);
        $original = $registry->definitions($f['portfolio'], $f['user']);
        $this->assertSame(array_column($original, 'name'), array_column($candidate, 'name'));
        foreach ($candidate as $tool) {
            $this->assertTrue($tool['strict']);
            $this->assertFalse($tool['parameters']['additionalProperties']);
            $this->assertSame(array_keys((array) $tool['parameters']['properties']), $tool['parameters']['required']);
            $hasReference = array_key_exists('property_query', (array) $tool['parameters']['properties']);
            $this->assertSame(in_array($tool['name'], ['get_financial_summary', 'list_rent_charges']), $hasReference);
        }
        $this->expectException(InvalidArgumentException::class);
        $registry->execute($f['portfolio'], $f['user'], $f['run'], 'list_rent_charges', $this->arguments('San Nicolás Valencia'));
    }

    public function test_http_composed_read_prepares_payment_in_two_calls_and_button_confirmation_stays_idempotent(): void
    {
        $f = $this->fixture();
        Http::fakeSequence()->push($this->tool('list_rent_charges', $this->arguments('San Nicolás Valencia')))
            ->push($this->tool('propose_rent_payment', ['rent_charge_id' => $f['september']->id,
                'amount' => '300.00', 'transaction_date' => '2026-09-20', 'payment_method' => null]));
        $response = $this->actingAs($f['user'])->postJson('/api/v1/assistant/conversations/'.$f['conversation']->id.'/messages', [
            'message' => 'He recibido 300 de septiembre de San Nicolás de Valencia',
        ])->assertOk()->assertJsonPath('assistant_message.metadata.kind', 'action_proposal');
        $this->assertDatabaseCount('transactions', 1);
        $id = $response->json('assistant_message.metadata.proposals.0.id');
        $this->getJson('/api/v1/assistant/proposals/'.$id)->assertOk();
        $this->postJson('/api/v1/assistant/proposals/'.$id.'/confirm', ['revision' => 1])->assertOk();
        $this->postJson('/api/v1/assistant/proposals/'.$id.'/confirm', ['revision' => 1])->assertOk();
        $this->assertDatabaseCount('transactions', 2);
        $this->assertSame('300.00', $f['september']->fresh()->paid_amount);
        Http::assertSentCount(2);
    }

    public function test_ambiguous_http_read_asks_concretely_without_another_provider_round(): void
    {
        $f = $this->fixture();
        Http::fakeSequence()->push($this->tool('list_rent_charges', $this->arguments('San Nicolás')));
        $response = $this->actingAs($f['user'])->postJson('/api/v1/assistant/conversations/'.$f['conversation']->id.'/messages', [
            'message' => '¿Qué queda por cobrar de San Nicolás?',
        ])->assertOk()->assertJsonPath('assistant_message.metadata.kind', 'clarification');
        $this->assertStringContainsString('Valencia', $response->json('assistant_message.content'));
        $this->assertStringContainsString('Madrid', $response->json('assistant_message.content'));
        $this->assertSame(0, AiActionProposal::count());
        Http::assertSentCount(1);
    }

    private function arguments(string $query): array
    {
        return ['property_query' => $query, 'property_id' => null, 'lease_id' => null, 'period' => '2026-09', 'status' => 'pending'];
    }

    private function tool(string $name, array $arguments): array
    {
        return ['model' => 'gpt-6-luna', 'output' => [['type' => 'function_call', 'call_id' => (string) Str::uuid(),
            'name' => $name, 'arguments' => json_encode($arguments)]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 10]];
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio = Portfolio::create(['name' => 'Synthetic references only', 'plan' => 'founder', 'trial_ends_at' => null]);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'San Nicolás', 'city' => 'Valencia', 'type' => 'housing', 'address_line' => 'Invented address']);
        $madrid = $portfolio->properties()->create(['name' => 'San Nicolás', 'city' => 'Madrid', 'type' => 'housing', 'address_line' => 'Invented address']);
        $lease = Lease::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id,
            'status' => 'active', 'start_date' => '2026-01-01', 'monthly_rent' => '550.00']);
        $august = $lease->charges()->create(['portfolio_id' => $portfolio->id, 'period' => '2026-08', 'amount' => '550.00',
            'due_date' => '2026-08-05', 'status' => 'partial', 'paid_amount' => '300.00']);
        $september = $lease->charges()->create(['portfolio_id' => $portfolio->id, 'period' => '2026-09', 'amount' => '550.00',
            'due_date' => '2026-09-05', 'status' => 'pending']);
        Transaction::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'lease_id' => $lease->id,
            'rent_charge_id' => $august->id, 'direction' => 'income', 'category' => 'rent', 'description' => 'Synthetic partial rent',
            'amount' => '300.00', 'transaction_date' => '2026-08-10', 'status' => 'paid']);
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        $run = AiRun::create(['user_id' => $user->id, 'portfolio_id' => $portfolio->id, 'conversation_id' => $conversation->id,
            'client_request_id' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'synthetic only'),
            'plan' => 'founder', 'routing_version' => 'test', 'billing_month' => today()->startOfMonth()]);

        return compact('user', 'portfolio', 'property', 'madrid', 'lease', 'august', 'september', 'conversation', 'run');
    }
}
