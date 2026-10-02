<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Services\AssistantLeasingQueries;
use App\Domain\Assistant\Services\PortfolioAssistantTools;
use App\Domain\Assistant\Services\ToolRegistry;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class AssistantLeasingToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai.key' => 'synthetic-key', 'assistant.enabled' => true, 'beta.assistant_validated' => true,
            'ai.actions.enabled' => true, 'assistant.model' => 'gpt-5.4-mini', 'ai.fallback_profile' => null]);
        Http::preventStrayRequests();
    }

    public function test_property_name_and_city_forms_resolve_only_one_owned_write_target(): void
    {
        $f = $this->fixture();
        $f['property']->update(['city' => 'Valencia']);
        $f['portfolio']->properties()->create(['name' => 'San Nicolás', 'city' => 'Madrid',
            'type' => 'housing', 'address_line' => 'Ficticia']);
        $other = $this->fixture();
        $other['property']->update(['city' => 'Valencia']);
        $run = AiRun::create(['user_id' => $f['user']->id, 'portfolio_id' => $f['portfolio']->id,
            'conversation_id' => $f['conversation']->id, 'client_request_id' => (string) Str::uuid(),
            'request_hash' => hash('sha256', 'synthetic city'), 'plan' => 'founder',
            'billing_month' => today()->startOfMonth(), 'routing_version' => 'test']);
        $registry = app(ToolRegistry::class);
        foreach (['San Nicolás de Valencia', 'San Nicolás Valencia', 'San Nicolás en Valencia'] as $query) {
            $found = $registry->execute($f['portfolio'], $f['user'], $run, 'search_properties', ['query' => $query]);
            $this->assertSame(1, $found['count']);
            $this->assertSame($f['property']->id, $found['properties'][0]['id']);
            $this->assertFalse($found['needs_clarification']);
            $this->assertStringNotContainsString((string) $other['property']->id, json_encode(array_column($found['properties'], 'id')));
        }
        $proposal = $registry->execute($f['portfolio'], $f['user'], $run, 'propose_property_note',
            ['property_id' => $f['property']->id, 'note' => 'Revisar portal']);
        $this->assertSame($f['property']->id, $proposal['proposal']['preview']['property']['id']);
        $this->assertSame('private-note-existing', $f['property']->fresh()->notes);

        $ambiguous = $registry->execute($f['portfolio'], $f['user'], $run, 'search_properties', ['query' => 'San Nicolás']);
        $this->assertSame(2, $ambiguous['count']);
        $this->assertTrue($ambiguous['needs_clarification']);
        Contact::create(['portfolio_id' => $f['portfolio']->id, 'name' => 'Juan Pérez']);
        Contact::create(['portfolio_id' => $f['portfolio']->id, 'name' => 'Juan López']);
        foreach (['Juan', 'piso de Juan'] as $query) {
            $related = $registry->execute($f['portfolio'], $f['user'], $run, 'search_properties', ['query' => $query]);
            $this->assertSame(0, $related['count']);
            $this->assertCount(2, $related['related_contacts']);
        }
        $this->expectException(InvalidArgumentException::class);
        $registry->execute($f['portfolio'], $f['user'], $run, 'propose_property_note',
            ['property_id' => $f['property']->id, 'note' => 'No debe prepararse']);
    }

    public function test_contact_search_is_accent_insensitive_and_never_returns_private_fields(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $r = $this->readTool($f, 'search_contacts', ['query' => 'Maria Lopez', 'property_id' => null]);
        $this->assertSame(1, $r['count']);
        $this->assertSame($f['contact']->id, $r['contacts'][0]['id']);
        $this->assertNotSame($other['contact']->id, $r['contacts'][0]['id']);
        $this->assertFalse($r['needs_clarification']);
        foreach (['private-email', 'private-tax-id', 'private-note', '600111222', 'private-address'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($r));
        }
        $this->assertSame($f['property']->id, $r['contacts'][0]['properties'][0]['id']);
    }

    public function test_same_names_require_clarification_and_property_filter_disambiguates(): void
    {
        $f = $this->fixture();
        Contact::create(['portfolio_id' => $f['portfolio']->id, 'name' => 'María López']);
        $this->assertTrue($this->readTool($f, 'search_contacts', ['query' => 'María López'])['needs_clarification']);
        $filtered = $this->readTool($f, 'search_contacts', ['query' => 'María López', 'property_id' => $f['property']->id]);
        $this->assertFalse($filtered['needs_clarification']);
        $f['contact']->delete();
        $this->assertSame(0, $this->readTool($f, 'search_contacts', ['query' => 'María López', 'property_id' => $f['property']->id])['count']);
    }

    public function test_contact_scan_truncation_cannot_produce_a_unique_resolution(): void
    {
        $f = $this->fixture();
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = ['portfolio_id' => $f['portfolio']->id, 'name' => 'Synthetic '.$i];
        }
        Contact::insert($rows);
        $r = $this->readTool($f, 'search_contacts', ['query' => 'María López']);
        $this->assertNull($r['count']);
        $this->assertTrue($r['truncated']);
        $this->assertTrue($r['needs_clarification']);
    }

    public function test_charge_summary_uses_recorded_payments_not_stale_cache_and_totals_exceed_sample(): void
    {
        $f = $this->fixture();
        $this->payment($f, '100.10');
        $this->payment($f, '99.90');
        // Cache deliberately stale: queries must derive the actual recorded balance.
        $f['charge']->update(['paid_amount' => '0.00']);
        for ($i = 1; $i <= 24; $i++) {
            $month = today()->startOfMonth()->subMonths($i);
            $f['lease']->charges()->create(['portfolio_id' => $f['portfolio']->id, 'period' => $month->format('Y-m'), 'due_date' => $month, 'amount' => '600.00']);
        }
        $r = $this->readTool($f, 'list_rent_charges', ['status' => 'pending']);
        $this->assertSame(25, $r['count']);
        $this->assertCount(20, $r['charges']);
        $this->assertTrue($r['truncated']);
        $this->assertSame('14800.00', $r['summary']['remaining_amount']);
        $this->assertSame('María López', $r['charges'][0]['tenants'][0]['name']);
        $this->assertSame(1, $r['charges'][0]['tenant_count']);
        $this->assertSame('María López', app(PortfolioAssistantTools::class)->execute($f['portfolio'], 'get_pending_items', ['kind' => 'rents'])['rents'][0]['tenants'][0]['name']);
        $this->assertSame('200.00', $r['summary']['paid_amount']);
        $legacy = app(PortfolioAssistantTools::class)->execute($f['portfolio'], 'get_pending_items', ['kind' => 'rents']);
        $this->assertEquals($r['summary']['remaining_amount'], $legacy['rents_summary']['pending_amount']);
        $this->assertSame(24, $legacy['rents_summary']['overdue_count']);
        $current = $this->readTool($f, 'list_rent_charges', ['status' => 'pending', 'period' => today()->format('Y-m')]);
        $this->assertSame('400.00', $current['charges'][0]['remaining_amount']);
        $this->assertSame(25, RentCharge::count()); // Read queries never generate missing charges.
    }

    public function test_contract_details_and_expirations_exclude_notes_and_other_portfolios(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $r = $this->readTool($f, 'get_lease_details', ['lease_id' => $f['lease']->id]);
        $this->assertSame('600.00', $r['lease']['monthly_rent']);
        $this->assertSame('1200.00', $r['lease']['deposit_amount']);
        $this->assertSame('María López', $r['lease']['participants'][0]['name']);
        $this->assertStringNotContainsString('private-note', json_encode($r));
        $this->assertNull($r['lease']['end_date']);
        $this->assertSame(0, $this->readTool($f, 'list_leases', ['status' => 'active', 'expires_within_days' => 30])['count']);
        $f['lease']->update(['end_date' => today()->addDays(30)]);
        $this->assertSame(1, $this->readTool($f, 'list_leases', ['status' => 'active', 'expires_within_days' => 30])['count']);
        $this->expectException(ModelNotFoundException::class);
        $this->readTool($f, 'get_lease_details', ['lease_id' => $other['lease']->id]);
    }

    public function test_foreign_property_and_unknown_arguments_are_rejected(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        try {
            $this->readTool($f, 'list_rent_charges', ['status' => 'all', 'property_id' => $other['property']->id]);
            $this->fail('Foreign property must be rejected.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
        $this->expectException(InvalidArgumentException::class);
        $this->readTool($f, 'search_contacts', ['query' => 'Maria', 'portfolio_id' => $other['portfolio']->id]);
    }

    public function test_phone_flow_prepares_then_confirms_without_sending_stored_phone_to_provider(): void
    {
        $f = $this->fixture();
        Http::fakeSequence()->push($this->tool('search_contacts', ['query' => 'Maria Lopez', 'property_id' => null]))
            ->push($this->tool('propose_contact_phone', ['contact_id' => $f['contact']->id, 'phone' => '+34 611 222 333']));
        $response = $this->send($f, 'Cambia el teléfono de María López a +34 611 222 333')->assertOk();
        $response->assertJsonPath('assistant_message.metadata.proposals.0.type', 'contact_phone');
        $this->assertSame('600111222', $f['contact']->fresh()->phone);
        $id = $response->json('assistant_message.metadata.proposals.0.id');
        $this->postJson("/api/v1/assistant/proposals/{$id}/confirm", ['revision' => 1])->assertOk();
        $this->assertSame('+34 611 222 333', $f['contact']->fresh()->phone);
        foreach (Http::recorded() as [$request]) {
            foreach (['600111222', 'private-email', 'private-tax-id', 'private-note'] as $secret) {
                $this->assertStringNotContainsString($secret, $request->body());
            }
        }
        Http::assertSentCount(2);
    }

    public function test_ambiguous_contact_returns_clarification_without_any_proposal(): void
    {
        $f = $this->fixture();
        Contact::create(['portfolio_id' => $f['portfolio']->id, 'name' => 'María López']);
        Http::fakeSequence()->push($this->tool('search_contacts', ['query' => 'Maria Lopez', 'property_id' => null]));
        $this->send($f, 'Cambia el teléfono de María López')->assertOk()->assertJsonPath('assistant_message.metadata.kind', 'clarification');
        $this->assertDatabaseCount('ai_action_proposals', 0);
        Http::assertSentCount(1);
    }

    public function test_rent_flow_records_partial_payment_linked_to_charge_once(): void
    {
        $f = $this->fixture();
        Http::fakeSequence()->push($this->tool('list_rent_charges', ['property_id' => $f['property']->id, 'lease_id' => null, 'period' => today()->format('Y-m'), 'status' => 'pending']))
            ->push($this->tool('propose_rent_payment', ['rent_charge_id' => $f['charge']->id, 'amount' => '150.25', 'transaction_date' => today()->toDateString(), 'payment_method' => null]));
        $response = $this->send($f, 'He cobrado 150,25 € de San Nicolás este mes')->assertOk();
        $this->assertDatabaseCount('transactions', 0);
        $id = $response->json('assistant_message.metadata.proposals.0.id');
        $this->postJson("/api/v1/assistant/proposals/{$id}/confirm", ['revision' => 1])->assertOk();
        $this->postJson("/api/v1/assistant/proposals/{$id}/confirm", ['revision' => 1])->assertOk();
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', ['rent_charge_id' => $f['charge']->id, 'lease_id' => $f['lease']->id, 'amount' => '150.25', 'direction' => 'income']);
        $this->assertSame('150.25', $f['charge']->fresh()->paid_amount);
        Http::assertSentCount(2);
    }

    public function test_note_flow_only_appends_after_confirmation_and_preserves_old_notes(): void
    {
        $f = $this->fixture();
        Http::fakeSequence()->push($this->tool('search_properties', ['query' => 'San Nicolas']))
            ->push($this->tool('propose_property_note', ['property_id' => $f['property']->id, 'note' => 'Revisar persiana el viernes.']));
        $response = $this->send($f, 'Añade una nota a San Nicolás: revisar persiana el viernes')->assertOk();
        $id = $response->json('assistant_message.metadata.proposals.0.id');
        $this->assertSame('private-note-existing', $f['property']->fresh()->notes);
        $this->postJson("/api/v1/assistant/proposals/{$id}/confirm", ['revision' => 1])->assertOk();
        $this->postJson("/api/v1/assistant/proposals/{$id}/confirm", ['revision' => 1])->assertOk();
        $this->assertStringStartsWith('private-note-existing', $f['property']->fresh()->notes);
        $this->assertSame(1, substr_count($f['property']->fresh()->notes, 'Revisar persiana el viernes.'));
        Http::assertSentCount(2);
    }

    public function test_new_proposals_require_current_turn_unique_resolution_and_never_expose_confirmation_tool(): void
    {
        $f = $this->fixture();
        $run = AiRun::create(['user_id' => $f['user']->id, 'portfolio_id' => $f['portfolio']->id, 'conversation_id' => $f['conversation']->id,
            'client_request_id' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'synthetic'), 'plan' => 'founder', 'billing_month' => today()->startOfMonth(), 'routing_version' => 'test']);
        $registry = app(ToolRegistry::class);
        $names = array_column($registry->definitions($f['portfolio'], $f['user']), 'name');
        $this->assertFalse(collect($names)->contains(fn ($name) => str_contains($name, 'confirm')));
        foreach ([['propose_contact_phone', ['contact_id' => $f['contact']->id, 'phone' => '611222333']], ['propose_property_note', ['property_id' => $f['property']->id, 'note' => 'No save']], ['propose_rent_payment', ['rent_charge_id' => $f['charge']->id, 'amount' => '1.00', 'transaction_date' => today()->toDateString(), 'payment_method' => null]]] as [$name, $arguments]) {
            try {
                $registry->execute($f['portfolio'], $f['user'], $run, $name, $arguments);
                $this->fail('A fabricated unresolved target must not be proposed.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        $this->assertDatabaseCount('ai_action_proposals', 0);
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio = Portfolio::create(['name' => 'Synthetic', 'currency' => 'EUR', 'plan' => 'founder', 'trial_ends_at' => null]);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'San Nicolás', 'type' => 'housing', 'address_line' => 'private-address', 'notes' => 'private-note-existing']);
        $contact = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'María López', 'phone' => '600111222', 'email' => 'private-email@example.test', 'tax_id' => 'private-tax-id', 'notes' => 'private-note-contact']);
        $lease = Lease::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active', 'start_date' => today()->subYears(3), 'monthly_rent' => '600.00', 'deposit_amount' => '1200.00', 'notes' => 'private-note-lease']);
        $lease->participants()->attach($contact, ['role' => 'tenant', 'is_primary' => true]);
        $charge = $lease->charges()->create(['portfolio_id' => $portfolio->id, 'period' => today()->format('Y-m'), 'due_date' => today(), 'amount' => '600.00']);
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);

        return compact('user', 'portfolio', 'property', 'contact', 'lease', 'charge', 'conversation');
    }

    private function payment(array $f, string $amount): void
    {
        Transaction::create(['portfolio_id' => $f['portfolio']->id, 'property_id' => $f['property']->id, 'lease_id' => $f['lease']->id,
            'rent_charge_id' => $f['charge']->id, 'direction' => 'income', 'category' => 'rent', 'description' => 'Synthetic', 'amount' => $amount, 'transaction_date' => today(), 'status' => 'paid']);
    }

    private function readTool(array $f, string $name, array $arguments): array
    {
        return app(AssistantLeasingQueries::class)->execute($f['portfolio'], $name, $arguments);
    }

    private function send(array $f, string $message)
    {
        return $this->actingAs($f['user'])->postJson('/api/v1/assistant/conversations/'.$f['conversation']->id.'/messages', ['message' => $message, 'client_request_id' => (string) Str::uuid()]);
    }

    private function tool(string $name, array $arguments): array
    {
        return ['output' => [['type' => 'function_call', 'call_id' => (string) Str::uuid(), 'name' => $name, 'arguments' => json_encode((object) $arguments)]], 'usage' => ['input_tokens' => 20, 'output_tokens' => 5]];
    }
}
