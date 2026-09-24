<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Services\PortfolioAssistantTools;
use App\Domain\Assistant\Services\ToolRegistry;
use App\Domain\Attention\Models\Issue;
use App\Domain\Attention\Models\Reminder;
use App\Domain\Attention\Services\PortfolioAttention;
use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PortfolioAttentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-24 18:00:00', 'UTC'));
        config(['assistant.enabled' => false, 'beta.assistant_validated' => false, 'services.openai.key' => null]);
        Http::preventStrayRequests();
    }

    public function test_pending_partial_and_full_payment_use_exact_recorded_money_not_cache(): void
    {
        $f = $this->fixture();
        $f['charge']->update(['status' => 'paid', 'paid_amount' => '600.00']);
        $s = $this->snapshot($f);
        $this->assertSame('rent_pending', $s['items'][0]['type']);
        $this->assertSame('today', $s['items'][0]['priority']['code']);
        $this->payment($f, '100.10');
        $this->payment($f, '99.91');
        $s = $this->snapshot($f);
        $this->assertSame('399.99', $s['items'][0]['amount']);
        $this->assertSame('rent_partial', $s['items'][0]['type']);
        $this->assertSame('200.01', $s['items'][0]['evidence']['recorded_paid']);
        $this->assertSame('399.99', $s['rents_summary']['pending_amount']);
        $f['charge']->update(['due_date' => today()->subDay()]);
        $s = $this->snapshot($f);
        $this->assertCount(1, $s['items']);
        $this->assertSame('rent_overdue', $s['items'][0]['type']);
        $this->assertSame('partial', $s['items'][0]['evidence']['payment_state']);
        $this->payment($f, '399.99');
        $this->assertSame(0, $this->snapshot($f)['total']);
        Http::assertNothingSent();
    }

    public function test_pending_expense_other_category_and_foreign_payments_do_not_settle_rent(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        foreach ([['status' => 'pending'], ['direction' => 'expense'], ['category' => 'other'], ['portfolio_id' => $other['portfolio']->id], ['property_id' => $other['property']->id], ['lease_id' => $other['lease']->id]] as $invalid) {
            $this->payment($f, '600.00', $invalid);
        }
        $this->assertSame('600.00', $this->snapshot($f)['rents_summary']['pending_amount']);
    }

    public function test_day_and_horizon_boundaries_are_inclusive_and_configurable(): void
    {
        $f = $this->fixture();
        foreach ([[-1, 'overdue'], [0, 'today'], [14, 'upcoming'], [15, null]] as [$days, $expected]) {
            $f['charge']->update(['due_date' => today()->addDays($days)]);
            $s = $this->snapshot($f);
            $this->assertSame($expected === null ? 0 : 1, $s['total']);
            if ($expected) {
                $this->assertSame($expected, $s['items'][0]['priority']['code']);
            }
        }
        config(['attention.rent_days' => 15]);
        $this->assertSame(1, $this->snapshot($f)['total']);
        $f['charge']->update(['due_date' => '2026-10-01']);
        $this->travelTo(CarbonImmutable::parse('2026-09-30 23:59:59', 'UTC'));
        $this->assertSame('upcoming', $this->snapshot($f)['items'][0]['priority']['code']);
        $this->travelTo(CarbonImmutable::parse('2026-10-01 00:00:00', 'UTC'));
        $this->assertSame('today', $this->snapshot($f)['items'][0]['priority']['code']);
        $this->travelTo(CarbonImmutable::parse('2026-10-02 00:00:00', 'UTC'));
        $this->assertSame('overdue', $this->snapshot($f)['items'][0]['priority']['code']);
    }

    public function test_contract_end_dates_status_changes_and_nulls_do_not_invent_events(): void
    {
        $f = $this->fixture();
        $f['charge']->update(['status' => 'cancelled']);
        $this->assertSame(0, $this->snapshot($f)['total']);
        foreach ([[60, 'upcoming'], [61, null], [0, 'today'], [-1, 'overdue']] as [$days, $expected]) {
            $f['lease']->update(['end_date' => today()->addDays($days)]);
            $s = $this->snapshot($f);
            $this->assertSame($expected === null ? 0 : 1, $s['total']);
            if ($expected) {
                $this->assertSame($expected, $s['items'][0]['priority']['code']);
            }
        }
        foreach (['cancelled', 'draft', 'ended'] as $status) {
            $f['lease']->update(['status' => $status]);
            $this->assertSame(0, $this->snapshot($f)['total']);
        }
        $f['lease']->update(['status' => 'active', 'end_date' => null]);
        $this->assertSame(0, $this->snapshot($f)['total']);
    }

    public function test_cancelled_archived_and_future_ended_lease_charges_are_excluded(): void
    {
        $f = $this->fixture();
        foreach (['draft', 'cancelled'] as $status) {
            $f['lease']->update(['status' => $status]);
            $this->assertSame(0, $this->snapshot($f)['total']);
        }
        $f['lease']->update(['status' => 'ended', 'end_date' => today()->subMonth()]);
        $this->assertSame(0, $this->snapshot($f)['total']);
        $f['charge']->update(['period' => '2026-08', 'due_date' => '2026-08-05']);
        $this->assertSame(1, $this->snapshot($f)['total']);
        $f['lease']->delete();
        $this->assertSame(0, $this->snapshot($f)['total']);
        $f['lease']->restore();
        $f['property']->delete();
        $this->assertSame(0, $this->snapshot($f)['total']);
    }

    public function test_dashboard_and_closed_tool_share_facts_without_provider_or_consent(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $s = $this->snapshot($f);
        $dashboard = $this->actingAs($f['user'])->getJson('/api/v1/dashboard')->assertOk();
        $this->assertSame($s['items'], $dashboard->json('attention'));
        $tool = $this->tool($f, ['property_id' => null, 'page' => 1]);
        $this->assertSame($s['items'], $tool['items']);
        $this->assertSame($s['rents_summary'], $tool['rents_summary']);
        $this->assertSame('/leases/'.$f['lease']->id, $tool['items'][0]['action']['path']);
        $this->assertSame($f['charge']->id, $tool['items'][0]['entity']['id']);
        $this->assertNotSame($other['charge']->id, $tool['items'][0]['entity']['id']);
        foreach (['private-address', 'private-notes', 'storage_key'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($tool));
        }
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('ai_run_steps', 0);
        $this->assertFalse(config('beta.assistant_validated'));
        Http::assertNothingSent();
    }

    public function test_tool_rejects_foreign_scope_unknown_arguments_and_run_actor(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        try {
            $this->tool($f, ['property_id' => $other['property']->id, 'page' => 1]);
            $this->fail('Foreign scope accepted');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
        foreach (['portfolio_id', 'user_id', 'as_of', 'priority', 'sql'] as $key) {
            try {
                $this->tool($f, ['page' => 1, $key => 'forbidden']);
                $this->fail('Unknown argument accepted');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        $run = new AiRun(['portfolio_id' => $f['portfolio']->id, 'user_id' => $other['user']->id]);
        try {
            app(ToolRegistry::class)->execute($f['portfolio'], $f['user'], $run, 'get_attention_items', ['page' => 1]);
            $this->fail('Foreign actor accepted');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $f['portfolio']->members()->detach($f['user']);
        try {
            $this->tool($f, ['page' => 1]);
            $this->fail('Former member accepted');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_simulated_assistant_flow_passes_domain_facts_and_source_without_writes(): void
    {
        // Test-only flags + intercepted HTTP. No real provider or environment changes.
        config(['assistant.enabled' => true, 'beta.assistant_validated' => true, 'services.openai.key' => 'synthetic-key', 'ai.fallback_profile' => null]);
        $f = $this->fixture();
        $f['portfolio']->update(['plan' => 'founder', 'trial_ends_at' => null]);
        $f['user']->forceFill(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')])->save();
        $conversation = AiConversation::create(['portfolio_id' => $f['portfolio']->id, 'user_id' => $f['user']->id]);
        Http::fakeSequence()->push(['output' => [['type' => 'function_call', 'call_id' => 'attention-1', 'name' => 'get_attention_items', 'arguments' => json_encode(['page' => 1, 'property_id' => null])]], 'usage' => ['input_tokens' => 20, 'output_tokens' => 5]])
            ->push(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['kind' => 'answer', 'basis' => 'portfolio_data', 'content' => 'Hay un alquiler pendiente de 600,00 € con fecha de hoy, según tus registros.'])]]]], 'usage' => ['input_tokens' => 20, 'output_tokens' => 5]]);
        $this->actingAs($f['user'])->postJson('/api/v1/assistant/conversations/'.$conversation->id.'/messages', ['message' => '¿Qué necesita mi atención?', 'client_request_id' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('assistant_message.metadata.sources.0.path', '/dashboard');
        $requests = Http::recorded();
        $input = $requests[1][0]['input'];
        $payload = collect($input)->firstWhere('type', 'function_call_output');
        $facts = json_decode($payload['output'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($this->snapshot($f)['items'], $facts['items']);
        $this->assertStringContainsString('ni recalcules saldos', $requests[1][0]['instructions']);
        $definition = collect($requests[0][0]['tools'])->firstWhere('name', 'get_attention_items');
        $this->assertTrue($definition['strict']);
        $this->assertFalse($definition['parameters']['additionalProperties']);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('ai_action_proposals', 0);
        Http::assertSentCount(2);
    }

    public function test_totals_and_stable_pages_are_not_limited_to_sample(): void
    {
        $f = $this->fixture();
        for ($i = 1; $i <= 24; $i++) {
            $date = today()->startOfMonth()->subMonths($i);
            $f['lease']->charges()->create(['portfolio_id' => $f['portfolio']->id, 'period' => $date->format('Y-m'), 'due_date' => $date, 'amount' => '0.01']);
        }
        $ids = [];
        for ($page = 1; $page <= 5; $page++) {
            $r = $this->tool($f, ['page' => $page]);
            $this->assertSame(25, $r['total']);
            $this->assertSame('600.24', $r['rents_summary']['pending_amount']);
            $this->assertCount(5, $r['items']);
            $this->assertSame($page < 5, $r['has_more']);
            $ids = [...$ids, ...array_column($r['items'], 'id')];
        }
        $this->assertCount(25, array_unique($ids));
        $this->assertSame($this->snapshot($f)['items'], $this->snapshot($f)['items']);
        $this->assertSame(25, $this->tool($f, ['page' => 6])['total']);
        $this->assertSame([], $this->tool($f, ['page' => 6])['items']);
        $legacy = app(PortfolioAssistantTools::class)->execute($f['portfolio'], 'get_pending_items', ['kind' => 'rents']);
        $this->assertSame('600.24', $legacy['rents_summary']['pending_amount']);
    }

    public function test_invalid_relationships_do_not_leak_cross_portfolio_items(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $f['lease']->update(['property_id' => $other['property']->id, 'end_date' => today()]);
        $this->assertSame(0, $this->snapshot($f)['total']);
        $f['lease']->update(['property_id' => $f['property']->id, 'portfolio_id' => $other['portfolio']->id]);
        $this->assertSame(0, $this->snapshot($f)['total']);
        Reminder::create(['portfolio_id' => $f['portfolio']->id, 'property_id' => $other['property']->id, 'title' => 'foreign', 'starts_at' => now()]);
        $this->assertSame(0, $this->snapshot($f)['total']);
    }

    public function test_existing_events_have_recorded_evidence_and_disappear_on_resolution(): void
    {
        $f = $this->fixture();
        $f['charge']->update(['status' => 'cancelled']);
        $issue = Issue::create(['portfolio_id' => $f['portfolio']->id, 'property_id' => $f['property']->id, 'title' => 'Grifo', 'reported_at' => today(), 'priority' => 'high', 'estimated_cost' => '99.99']);
        $reminder = Reminder::create(['portfolio_id' => $f['portfolio']->id, 'title' => 'Visita', 'starts_at' => today()->addDays(14)->endOfDay()]);
        $doc = Document::create(['portfolio_id' => $f['portfolio']->id, 'name' => 'Seguro', 'storage_key' => 'private-key', 'original_filename' => 'private-file.pdf', 'mime_type' => 'application/pdf', 'size' => 10, 'uploaded_by' => $f['user']->id, 'expires_at' => today()->addDays(30)]);
        $s = $this->snapshot($f);
        $this->assertSame(3, $s['total']);
        $this->assertSame('review', $s['items'][0]['priority']['code']);
        $this->assertNull($s['items'][0]['date']);
        $this->assertNull($s['items'][0]['amount']); // Estimated cost is not debt.
        $this->assertStringNotContainsString('private-', json_encode($s));
        $reminder->update(['starts_at' => today()->addDays(15)]);
        $doc->update(['expires_at' => today()->addDays(31)]);
        $this->assertSame(1, $this->snapshot($f)['total']);
        $reminder->update(['starts_at' => today()->subDay(), 'completed_at' => now()]);
        $issue->update(['status' => 'resolved']);
        $this->assertSame(0, $this->snapshot($f)['total']);
        $doc->update(['expires_at' => today()->subDay()]);
        $this->assertSame('overdue', $this->snapshot($f)['items'][0]['priority']['code']);
        $doc->update(['expires_at' => null]);
        $this->assertSame([], $this->snapshot($f)['items']);
    }

    private function fixture(): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Synthetic', 'currency' => 'EUR']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Centro', 'type' => 'housing', 'address_line' => 'private-address', 'notes' => 'private-notes']);
        $lease = Lease::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active', 'start_date' => '2020-01-01', 'monthly_rent' => '600.00']);
        $charge = $lease->charges()->create(['portfolio_id' => $portfolio->id, 'period' => '2026-09', 'due_date' => today(), 'amount' => '600.00']);

        return compact('user', 'portfolio', 'property', 'lease', 'charge');
    }

    private function snapshot(array $f): array
    {
        return app(PortfolioAttention::class)->snapshot($f['portfolio']);
    }

    private function tool(array $f, array $arguments): array
    {
        $run = new AiRun(['portfolio_id' => $f['portfolio']->id, 'user_id' => $f['user']->id]);

        return app(ToolRegistry::class)->execute($f['portfolio'], $f['user'], $run, 'get_attention_items', $arguments);
    }

    private function payment(array $f, string $amount, array $overrides = []): void
    {
        Transaction::create(array_replace(['portfolio_id' => $f['portfolio']->id, 'property_id' => $f['property']->id, 'lease_id' => $f['lease']->id, 'rent_charge_id' => $f['charge']->id,
            'direction' => 'income', 'category' => 'rent', 'description' => 'Synthetic', 'amount' => $amount, 'transaction_date' => today(), 'status' => 'paid'], $overrides));
    }
}
