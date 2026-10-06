<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiActionProposal;
use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Services\AssistantConversationContext;
use App\Domain\Assistant\Services\ToolRegistry;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AssistantCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.actions.creation_enabled' => true, 'assistant.enabled' => true, 'beta.assistant_validated' => true,
            'services.openai.key' => 'synthetic-offline-key']);
        Http::preventStrayRequests();
    }

    public function test_property_preview_is_encrypted_and_confirmation_creates_exactly_once_with_valuation(): void
    {
        $f = $this->fixture();
        $proposal = $this->prepare($f, 'propose_property', $this->propertyInput());
        $this->assertSame('property_create', $proposal['type']);
        $this->assertSame('119999.95', $proposal['preview']['current_value']);
        $this->assertSame(1, $f['portfolio']->properties()->count());
        $raw = DB::table('ai_action_proposals')->where('id', $proposal['id'])->value('payload');
        $this->assertStringNotContainsString('Invented street', $raw);
        $this->actingAs($f['user'])->getJson('/api/v1/assistant/proposals/'.$proposal['id'])->assertOk();
        $first = $this->postJson('/api/v1/assistant/proposals/'.$proposal['id'].'/confirm', ['revision' => 1])->assertOk();
        $second = $this->postJson('/api/v1/assistant/proposals/'.$proposal['id'].'/confirm', ['revision' => 1])->assertOk();
        $this->assertSame($first->json('proposal.result.property_id'), $second->json('proposal.result.property_id'));
        $this->assertSame(2, $f['portfolio']->properties()->count());
        $property = $f['portfolio']->properties()->findOrFail($first->json('proposal.result.property_id'));
        $this->assertSame('119999.95', $property->current_value);
        $this->assertSame(1, $property->valuations()->count());
        Http::assertNothingSent();
    }

    public function test_missing_property_fields_return_specific_question_and_never_invent_address(): void
    {
        $f = $this->fixture();
        Http::fakeSequence()->push($this->providerCall('propose_property', ['name' => 'Casa Muro', 'type' => null, 'address_line' => null,
            'city' => null, 'purchase_price' => null, 'current_value' => null]));
        $r = $this->actingAs($f['user'])->postJson('/api/v1/assistant/conversations/'.$f['conversation']->id.'/messages', ['message' => 'Crea una propiedad llamada Casa Muro'])->assertOk();
        $this->assertStringContainsString('dirección', $r->json('assistant_message.content'));
        $this->assertStringContainsString('tipo de inmueble', $r->json('assistant_message.content'));
        $this->assertSame(0, AiActionProposal::count());
        $this->assertSame(1, $f['portfolio']->properties()->count());
        Http::assertSentCount(1);
    }

    public function test_contact_is_a_reviewable_creation_not_an_implicit_tenant_link(): void
    {
        $f = $this->fixture();
        $proposal = $this->prepare($f, 'propose_contact', ['name' => 'Laura Prueba', 'kind' => null,
            'email' => 'laura@synthetic.test', 'phone' => '+34 612 345 678']);
        $this->assertSame('person', $proposal['preview']['kind']);
        $this->assertSame(1, Contact::count());
        $this->actingAs($f['user'])->postJson('/api/v1/assistant/proposals/'.$proposal['id'].'/confirm', ['revision' => 1])->assertOk();
        $this->postJson('/api/v1/assistant/proposals/'.$proposal['id'].'/confirm', ['revision' => 1])->assertOk();
        $this->assertSame(2, Contact::count());
        $this->assertDatabaseCount('lease_participants', 0);
        Http::assertNothingSent();
    }

    public function test_http_property_creation_only_remembers_the_new_id_after_separate_confirmation(): void
    {
        $f = $this->fixture();
        Http::fakeSequence()->push($this->providerCall('propose_property', $this->propertyInput()));
        $r = $this->actingAs($f['user'])->postJson('/api/v1/assistant/conversations/'.$f['conversation']->id.'/messages',
            ['message' => 'Crea Casa Muro, vivienda en Invented street 42, Valencia, compra 100000.35 y valor 119999.95'])->assertOk();
        $id = $r->json('assistant_message.metadata.proposals.0.id');
        $this->assertSame('property_create', $r->json('assistant_message.metadata.proposals.0.type'));
        $this->assertSame(1, $f['portfolio']->properties()->count());
        $memory = app(AssistantConversationContext::class);
        $this->assertNull($memory->previousProperty($f['portfolio'], $f['user'], $f['run']));
        $confirmation = $this->postJson('/api/v1/assistant/proposals/'.$id.'/confirm', ['revision' => 1])->assertOk();
        $newPropertyId = $confirmation->json('proposal.result.property_id');
        $this->assertSame($newPropertyId, $memory->previousProperty($f['portfolio'], $f['user'], $f['run']));
        $f['portfolio']->properties()->findOrFail($newPropertyId)->delete();
        $this->assertNull($memory->previousProperty($f['portfolio'], $f['user'], $f['run']));
        Http::assertSentCount(1);
    }

    public function test_chat_yes_cannot_confirm_a_creation_and_cancelled_proposal_cannot_be_executed(): void
    {
        $f = $this->fixture();
        Http::fakeSequence()->push($this->providerCall('propose_property', $this->propertyInput()))
            ->push(['model' => 'gpt-6-luna', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text',
                'text' => json_encode(['kind' => 'read_only', 'basis' => 'none', 'content' => 'Usa el botón de la propuesta.'])]]]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 10]]);
        $url = '/api/v1/assistant/conversations/'.$f['conversation']->id.'/messages';
        $r = $this->actingAs($f['user'])->postJson($url, ['message' => 'Crea el inmueble con todos los datos indicados'])->assertOk();
        $id = $r->json('assistant_message.metadata.proposals.0.id');
        $this->postJson($url, ['message' => 'Sí, confírmalo y guárdalo'])->assertOk();
        $this->assertSame('pending', AiActionProposal::findOrFail($id)->status);
        $this->assertSame(1, $f['portfolio']->properties()->count());
        $this->postJson('/api/v1/assistant/proposals/'.$id.'/cancel', ['revision' => 1])->assertOk();
        $this->postJson('/api/v1/assistant/proposals/'.$id.'/confirm', ['revision' => 1])->assertConflict();
        $this->assertSame(1, $f['portfolio']->properties()->count());
        Http::assertSentCount(2);
    }

    public function test_lease_preview_preserves_reviewed_primary_participant_order(): void
    {
        $f = $this->fixture();
        $second = Contact::create(['portfolio_id' => $f['portfolio']->id, 'name' => 'Laura Prueba', 'kind' => 'person']);
        $input = $this->leaseInput();
        $input['contact_queries'] = ['Laura Prueba', 'Pedro Prueba'];
        $proposal = $this->prepare($f, 'propose_lease', $input);
        $this->assertSame([$second->id, $f['contact']->id], array_column($proposal['preview']['contacts'], 'id'));
        $this->actingAs($f['user'])->postJson('/api/v1/assistant/proposals/'.$proposal['id'].'/confirm', ['revision' => 1])->assertOk();
        $this->assertTrue((bool) Lease::first()->participants()->findOrFail($second->id)->pivot->is_primary);
    }

    public function test_lease_resolves_existing_targets_and_confirmation_creates_draft_without_charges(): void
    {
        $f = $this->fixture();
        $proposal = $this->prepare($f, 'propose_lease', $this->leaseInput());
        $this->assertSame('draft', $proposal['preview']['status']);
        $this->assertSame('550.35', $proposal['preview']['monthly_rent']);
        $this->assertSame($f['contact']->id, $proposal['preview']['contacts'][0]['id']);
        $this->assertSame(0, Lease::count());
        $this->actingAs($f['user'])->postJson('/api/v1/assistant/proposals/'.$proposal['id'].'/confirm', ['revision' => 1])->assertOk();
        $this->postJson('/api/v1/assistant/proposals/'.$proposal['id'].'/confirm', ['revision' => 1])->assertOk();
        $this->assertSame(1, Lease::count());
        $this->assertSame('draft', Lease::first()->status);
        $this->assertDatabaseCount('rent_charges', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('lease_participants', 1);
    }

    public function test_lease_can_include_multiple_unique_contacts_without_silently_creating_people(): void
    {
        $f = $this->fixture();
        $second = Contact::create(['portfolio_id' => $f['portfolio']->id, 'name' => 'Laura Prueba', 'kind' => 'person']);
        $input = $this->leaseInput();
        $input['contact_queries'][] = 'Laura Prueba';
        $proposal = $this->prepare($f, 'propose_lease', $input);
        $this->assertEqualsCanonicalizing([$f['contact']->id, $second->id], $proposal['preview']['contact_ids']);
        $this->assertSame(2, Contact::count());
        $this->assertSame(0, Lease::count());
    }

    public function test_ambiguity_foreign_and_missing_contact_never_prepare_a_contract(): void
    {
        $f = $this->fixture();
        Contact::create(['portfolio_id' => $f['portfolio']->id, 'name' => 'Pedro Prueba Dos', 'kind' => 'person']);
        $input = $this->leaseInput();
        $input['contact_queries'] = ['Pedro'];
        $r = app(ToolRegistry::class)->execute($f['portfolio'], $f['user'], $f['run'], 'propose_lease', $input);
        $this->assertStringContainsString('varios contactos', $r['clarification']);
        $other = Portfolio::create(['name' => 'Foreign synthetic']);
        Contact::create(['portfolio_id' => $other->id, 'name' => 'Persona Externa', 'kind' => 'person', 'phone' => 'SECRET']);
        $input['contact_queries'] = ['Persona Externa'];
        $r = app(ToolRegistry::class)->execute($f['portfolio'], $f['user'], $f['run'], 'propose_lease', $input);
        $this->assertStringContainsString('No encuentro', $r['clarification']);
        $this->assertStringNotContainsString('SECRET', json_encode($r));
        $this->assertSame(0, AiActionProposal::count());
        $this->assertSame(0, Lease::count());
    }

    public function test_duplicate_names_are_not_silently_created_and_racing_duplicate_stales_preview(): void
    {
        $f = $this->fixture();
        $r = app(ToolRegistry::class)->execute($f['portfolio'], $f['user'], $f['run'], 'propose_contact', ['name' => 'Pedro Prueba', 'kind' => 'person', 'email' => null, 'phone' => null]);
        $this->assertStringContainsString('Ya existe', $r['clarification']);
        $proposal = $this->prepare($f, 'propose_property', $this->propertyInput());
        $f['portfolio']->properties()->create(['name' => 'Casa Muro', 'type' => 'housing', 'address_line' => 'Another synthetic street']);
        $this->actingAs($f['user'])->postJson('/api/v1/assistant/proposals/'.$proposal['id'].'/confirm', ['revision' => 1])->assertConflict();
        $this->assertSame(2, $f['portfolio']->properties()->count());
    }

    public function test_changed_or_archived_lease_target_requires_fresh_review(): void
    {
        $f = $this->fixture();
        $proposal = $this->prepare($f, 'propose_lease', $this->leaseInput());
        $f['contact']->update(['name' => 'Pedro Modificado']);
        $this->actingAs($f['user'])->postJson('/api/v1/assistant/proposals/'.$proposal['id'].'/confirm', ['revision' => 1])->assertConflict();
        $f['contact']->delete();
        $this->postJson('/api/v1/assistant/proposals/'.$proposal['id'].'/confirm', ['revision' => 1])->assertConflict();
        $this->assertSame(0, Lease::count());
    }

    public function test_revision_cannot_activate_lease_or_change_its_targets(): void
    {
        $f = $this->fixture();
        $proposal = $this->prepare($f, 'propose_lease', $this->leaseInput());
        $url = '/api/v1/assistant/proposals/'.$proposal['id'];
        $this->actingAs($f['user'])->postJson($url.'/revise', ['revision' => 1, 'status' => 'active'])->assertUnprocessable();
        $this->postJson($url.'/revise', ['revision' => 1, 'contact_ids' => []])->assertUnprocessable();
        $this->postJson($url.'/revise', ['revision' => 1, 'monthly_rent' => '550.37'])->assertOk()->assertJsonPath('proposal.revision', 2);
        $this->postJson($url.'/confirm', ['revision' => 1])->assertConflict();
        $this->postJson($url.'/confirm', ['revision' => 2])->assertOk();
        $this->assertSame('550.37', Lease::first()->monthly_rent);
        $this->assertDatabaseCount('rent_charges', 0);
    }

    public function test_new_tools_stay_unavailable_to_public_accounts_by_default(): void
    {
        $f = $this->fixture();
        config(['ai.actions.creation_enabled' => false]);
        $names = array_column(app(ToolRegistry::class)->definitions($f['portfolio'], $f['user']), 'name');
        $this->assertNotContains('propose_property', $names);
        $this->assertContains('propose_expense', $names);
        $this->actingAs($f['user'])->getJson('/api/v1/assistant/conversations')->assertOk()->assertJsonPath('capabilities.creation_actions', false);
        $this->assertSame(0, AiActionProposal::count());
    }

    public function test_authenticated_mfa_session_can_preview_recover_and_confirm_creation_exactly_once(): void
    {
        $f = $this->fixture();
        $totp = new Google2FA;
        $f['user']->forceFill(['password' => 'synthetic-pass-123', 'two_factor_secret' => $totp->generateSecretKey(),
            'two_factor_confirmed_at' => now(), 'two_factor_method' => 'authenticator'])->save();
        config(['sanctum.stateful' => [parse_url(config('app.url'), PHP_URL_HOST).(parse_url(config('app.url'), PHP_URL_PORT) ? ':'.parse_url(config('app.url'), PHP_URL_PORT) : '')]]);
        Auth::guard('web')->logout();
        $this->withHeader('Origin', config('app.url'))->withSession([])->postJson('/api/v1/auth/login',
            ['email' => $f['user']->email, 'password' => 'synthetic-pass-123'])->assertOk()->assertJsonPath('two_factor_required', true);
        $this->getJson('/api/v1/assistant/conversations/'.$f['conversation']->id)->assertUnauthorized();
        Http::assertNothingSent();
        $this->postJson('/api/v1/auth/two-factor', ['code' => $totp->oathTotp($f['user']->fresh()->two_factor_secret, intdiv(now()->timestamp, 30))])->assertOk();
        $this->assertAuthenticatedAs($f['user']);
        Http::fakeSequence()->push($this->providerCall('propose_property', $this->propertyInput()));
        $r = $this->postJson('/api/v1/assistant/conversations/'.$f['conversation']->id.'/messages', ['message' => 'Crea Casa Muro, vivienda en Invented street 42 de Valencia'])->assertOk();
        $id = $r->json('assistant_message.metadata.proposals.0.id');
        $this->getJson('/api/v1/assistant/runs/'.$r->json('run.id'))->assertOk();
        $this->getJson('/api/v1/assistant/proposals/'.$id)->assertOk();
        $this->assertSame(1, $f['portfolio']->properties()->count());
        $first = $this->postJson('/api/v1/assistant/proposals/'.$id.'/confirm', ['revision' => 1])->assertOk();
        $second = $this->postJson('/api/v1/assistant/proposals/'.$id.'/confirm', ['revision' => 1])->assertOk();
        $this->assertSame($first->json('proposal.result.property_id'), $second->json('proposal.result.property_id'));
        $this->assertSame(2, $f['portfolio']->properties()->count());
        Http::assertSentCount(1);
    }

    public function test_quota_changed_since_preview_prevents_creation_and_foreign_user_cannot_confirm(): void
    {
        $f = $this->fixture();
        $proposal = $this->prepare($f, 'propose_property', $this->propertyInput());
        $stranger = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $this->actingAs($stranger)->postJson('/api/v1/assistant/proposals/'.$proposal['id'].'/confirm', ['revision' => 1])->assertNotFound();
        config(['plans.founder.property_limit' => 1]);
        $this->actingAs($f['user'])->postJson('/api/v1/assistant/proposals/'.$proposal['id'].'/confirm', ['revision' => 1])->assertUnprocessable();
        $this->assertSame(1, $f['portfolio']->properties()->count());
    }

    public function test_new_tool_schemas_are_closed_and_no_confirmation_tool_is_registered(): void
    {
        $f = $this->fixture();
        foreach (app(ToolRegistry::class)->definitions($f['portfolio'], $f['user']) as $tool) {
            $this->assertTrue($tool['strict']);
            $this->assertFalse($tool['parameters']['additionalProperties']);
            $this->assertSame(array_keys((array) $tool['parameters']['properties']), $tool['parameters']['required']);
            $this->assertStringNotContainsString('confirm', $tool['name']);
        }
    }

    private function prepare(array $f, string $tool, array $input): array
    {
        return app(ToolRegistry::class)->execute($f['portfolio'], $f['user'], $f['run'], $tool, $input)['proposal'];
    }

    private function propertyInput(): array
    {
        return ['name' => 'Casa Muro', 'type' => 'housing', 'address_line' => 'Invented street 42', 'city' => 'Valencia', 'purchase_price' => '100000.35', 'current_value' => '119999.95'];
    }

    private function leaseInput(): array
    {
        return ['property_query' => 'Piso Centro Alicante', 'contact_queries' => ['Pedro Prueba'], 'start_date' => '2026-10-01',
            'end_date' => null, 'monthly_rent' => '550.35', 'deposit_amount' => '550.35', 'payment_day' => 5];
    }

    private function providerCall(string $tool, array $input): array
    {
        return ['model' => 'gpt-6-luna', 'output' => [['type' => 'function_call', 'call_id' => (string) Str::uuid(),
            'name' => $tool, 'arguments' => json_encode($input)]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 10]];
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio = Portfolio::create(['name' => 'Synthetic creation only', 'plan' => 'founder', 'trial_ends_at' => null]);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Piso Centro', 'type' => 'housing', 'city' => 'Alicante', 'address_line' => 'Invented old street']);
        $contact = Contact::create(['portfolio_id' => $portfolio->id, 'kind' => 'person', 'name' => 'Pedro Prueba']);
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        $run = AiRun::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id, 'conversation_id' => $conversation->id,
            'client_request_id' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'synthetic'), 'plan' => 'founder', 'routing_version' => 'test', 'billing_month' => today()->startOfMonth()]);

        return compact('user', 'portfolio', 'property', 'contact', 'conversation', 'run');
    }
}
