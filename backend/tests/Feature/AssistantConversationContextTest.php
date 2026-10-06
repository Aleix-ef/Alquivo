<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Services\AssistantConversationContext;
use App\Domain\Assistant\Services\ToolRegistry;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class AssistantConversationContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.enabled' => true, 'beta.assistant_validated' => true, 'services.openai.key' => 'synthetic-offline-key']);
        Http::preventStrayRequests();
    }

    public function test_partial_payment_then_tenants_uses_reference_but_rereads_changed_participants(): void
    {
        $f = $this->fixture();
        Http::fakeSequence()->push($this->tool('list_rent_charges', ['property_id' => $f['property']->id, 'property_query' => null,
            'lease_id' => null, 'period' => today()->format('Y-m'), 'status' => 'pending']))
            ->push($this->tool('propose_rent_payment', ['rent_charge_id' => $f['charge']->id, 'amount' => '300.00',
                'transaction_date' => today()->toDateString(), 'payment_method' => null]))
            ->push($this->tool('get_lease_details', ['lease_id' => $f['lease']->id]))->push($this->reply('Laura Actual es la inquilina registrada.'));
        $r = $this->send($f, 'He recibido 300 de los 550 de Piso Centro este mes')->assertOk();
        $this->assertSame($f['property']->id, $r->json('assistant_message.metadata.property_reference_id'));
        $this->assertSame('0.00', $f['charge']->fresh()->paid_amount);
        $this->assertDatabaseCount('transactions', 0);
        $new = Contact::create(['portfolio_id' => $f['portfolio']->id, 'name' => 'Laura Actual', 'kind' => 'person']);
        $f['lease']->participants()->sync([$new->id => ['role' => 'tenant', 'is_primary' => true]]);
        $this->send($f, '¿Quiénes son los inquilinos?')->assertOk()->assertJsonPath('assistant_message.content', 'Laura Actual es la inquilina registrada.');
        Http::assertSent(fn ($request) => str_contains(json_encode($request['input']), 'property_id='.$f['property']->id));
        Http::assertSent(fn ($request) => str_contains(json_encode($request['input']), 'Laura Actual'));
        $this->assertSame('0.00', $f['charge']->fresh()->paid_amount);
        $raw = DB::table('ai_messages')->where('role', 'assistant')->latest('id')->first();
        $this->assertNull($raw->metadata);
        $this->assertStringNotContainsString('property_reference_id', $raw->metadata_encrypted);
    }

    public function test_name_bound_financial_query_can_supply_reference_without_an_extra_model_search(): void
    {
        $f = $this->fixture();
        Http::fakeSequence()->push($this->tool('get_financial_summary', ['property_id' => null, 'property_query' => 'Piso Centro Alicante',
            'time_scope' => 'all_time', 'from' => null, 'to' => null]))->push($this->reply('No constan movimientos pagados registrados.'));
        $r = $this->send($f, '¿Cuánto he cobrado en total de Piso Centro?')->assertOk();
        $this->assertSame($f['property']->id, $r->json('assistant_message.metadata.property_reference_id'));
        $this->assertSame($f['property']->id, app(AssistantConversationContext::class)->previousProperty($f['portfolio'], $f['user'], $f['run']));
    }

    public function test_context_is_not_shared_with_new_conversation_or_other_user(): void
    {
        $f = $this->fixture();
        $this->seedReference($f);
        $other = AiConversation::create(['portfolio_id' => $f['portfolio']->id, 'user_id' => $f['user']->id]);
        $run = $this->newRun($f['user'], $f['portfolio'], $other);
        $this->assertNull(app(AssistantConversationContext::class)->previousProperty($f['portfolio'], $f['user'], $run));
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->getJson('/api/v1/assistant/conversations/'.$f['conversation']->id)->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_expired_or_archived_reference_is_not_reused(): void
    {
        $f = $this->fixture();
        $this->seedReference($f);
        $memory = app(AssistantConversationContext::class);
        $this->assertSame($f['property']->id, $memory->previousProperty($f['portfolio'], $f['user'], $f['run']));
        $this->travel(31)->minutes();
        $this->assertNull($memory->previousProperty($f['portfolio'], $f['user'], $f['run']));
        $this->travelBack();
        $f['property']->delete();
        $this->assertNull($memory->previousProperty($f['portfolio'], $f['user'], $f['run']));
    }

    public function test_failed_or_interrupted_newer_user_turn_prevents_reusing_an_older_reference(): void
    {
        $f = $this->fixture();
        $this->seedReference($f);
        $memory = app(AssistantConversationContext::class);
        $this->assertSame($f['property']->id, $memory->previousProperty($f['portfolio'], $f['user'], $f['run']));
        $failed = $f['conversation']->messages()->create(['role' => 'user', 'content' => 'Ahora quiero consultar Otro Piso, no Piso Centro']);
        $failedRun = $this->newRun($f['user'], $f['portfolio'], $f['conversation']);
        $failedRun->update(['status' => 'failed', 'user_message_id' => $failed->id]);
        $current = $f['conversation']->messages()->create(['role' => 'user', 'content' => '¿Quiénes viven ahí?']);
        $currentRun = $this->newRun($f['user'], $f['portfolio'], $f['conversation']);
        $currentRun->update(['user_message_id' => $current->id]);
        $this->assertNull($memory->previousProperty($f['portfolio'], $f['user'], $currentRun));
        Http::assertNothingSent();
    }

    public function test_ambiguous_turn_clears_reference_instead_of_reusing_an_older_property(): void
    {
        $f = $this->fixture();
        $this->seedReference($f);
        $f['portfolio']->properties()->create(['name' => 'San Nicolás', 'type' => 'housing', 'city' => 'Valencia', 'address_line' => 'Synthetic']);
        $f['portfolio']->properties()->create(['name' => 'San Nicolás', 'type' => 'housing', 'city' => 'Madrid', 'address_line' => 'Synthetic']);
        Http::fakeSequence()->push($this->tool('search_properties', ['query' => 'San Nicolás']));
        $this->send($f, '¿Cuánto queda por cobrar de San Nicolás?')->assertOk()->assertJsonPath('assistant_message.metadata.property_reference_id', null);
        $this->assertNull(app(AssistantConversationContext::class)->previousProperty($f['portfolio'], $f['user'], $f['run']));
    }

    public function test_unproven_metadata_or_foreign_property_cannot_seed_context(): void
    {
        $f = $this->fixture();
        $f['conversation']->messages()->create(['role' => 'assistant', 'content' => 'Unproven', 'metadata' => ['property_reference_id' => $f['property']->id]]);
        $memory = app(AssistantConversationContext::class);
        $this->assertNull($memory->previousProperty($f['portfolio'], $f['user'], $f['run']));
        $other = Portfolio::create(['name' => 'Foreign synthetic']);
        $foreign = $other->properties()->create(['name' => 'External', 'type' => 'housing', 'address_line' => 'Secret synthetic']);
        $this->seedReference($f, $foreign->id);
        $this->assertNull($memory->previousProperty($f['portfolio'], $f['user'], $f['run']));
    }

    public function test_explicit_http_property_context_takes_priority_and_no_memory_grants_write_evidence(): void
    {
        $f = $this->fixture();
        $this->seedReference($f);
        $other = $f['portfolio']->properties()->create(['name' => 'Otra casa', 'type' => 'housing', 'address_line' => 'Synthetic']);
        Http::fakeSequence()->push($this->reply('greeting', 'app_help'));
        $this->send($f, 'Hola', ['property_id' => $other->id])->assertOk();
        Http::assertSent(fn ($request) => str_contains($request['instructions'], 'ID '.$other->id)
            && ! str_contains(json_encode($request['input']), 'Referencia breve'));
        $this->expectException(InvalidArgumentException::class);
        app(ToolRegistry::class)->execute($f['portfolio'], $f['user'], $f['run'], 'propose_expense', [
            'property_id' => $f['property']->id, 'amount' => '84.00', 'category' => 'maintenance', 'description' => 'Repair',
            'transaction_date' => today()->toDateString(), 'status' => 'pending']);
    }

    private function seedReference(array $f, ?int $id = null): void
    {
        $message = $f['conversation']->messages()->create(['role' => 'assistant', 'content' => 'Synthetic reference',
            'metadata' => ['property_reference_id' => $id ?? $f['property']->id]]);
        $origin = $this->newRun($f['user'], $f['portfolio'], $f['conversation']);
        $origin->update(['status' => 'completed', 'assistant_message_id' => $message->id]);
    }

    private function send(array $f, string $message, array $extra = [])
    {
        return $this->actingAs($f['user'])->postJson('/api/v1/assistant/conversations/'.$f['conversation']->id.'/messages', ['message' => $message, ...$extra]);
    }

    private function tool(string $name, array $args): array
    {
        return ['model' => 'gpt-6-luna', 'output' => [['type' => 'function_call', 'call_id' => (string) Str::uuid(), 'name' => $name, 'arguments' => json_encode($args)]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 10]];
    }

    private function reply(string $content, string $basis = 'portfolio_data'): array
    {
        return ['model' => 'gpt-6-luna', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text',
            'text' => json_encode(['kind' => 'answer', 'basis' => $basis, 'content' => $content])]]]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 10]];
    }

    private function newRun(User $user, Portfolio $portfolio, AiConversation $conversation): AiRun
    {
        return AiRun::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id, 'conversation_id' => $conversation->id,
            'client_request_id' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'synthetic'), 'plan' => 'founder', 'routing_version' => 'test', 'billing_month' => today()->startOfMonth()]);
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio = Portfolio::create(['name' => 'Synthetic memory only', 'plan' => 'founder', 'trial_ends_at' => null]);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Piso Centro', 'type' => 'housing', 'city' => 'Alicante', 'address_line' => 'Invented']);
        $person = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'Pedro Prueba', 'kind' => 'person']);
        $lease = Lease::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active', 'start_date' => today()->startOfMonth(), 'monthly_rent' => '550.00']);
        $lease->participants()->attach($person, ['role' => 'tenant', 'is_primary' => true]);
        $charge = $lease->charges()->create(['portfolio_id' => $portfolio->id, 'period' => today()->format('Y-m'), 'amount' => '550.00', 'due_date' => today(), 'status' => 'pending']);
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        $run = $this->newRun($user, $portfolio, $conversation);

        return compact('user', 'portfolio', 'property', 'lease', 'charge', 'conversation', 'run');
    }
}
