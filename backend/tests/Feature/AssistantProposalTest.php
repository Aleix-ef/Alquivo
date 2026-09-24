<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiActionProposal;
use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Services\ActionProposalService;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AssistantProposalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.enabled' => true, 'ai.actions.enabled' => true, 'beta.assistant_validated' => true]);
        Http::preventStrayRequests();
    }

    public function test_preview_edit_explicit_confirmation_and_replay_create_exactly_one_expense(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $proposal = $this->propose($portfolio, $user, $run, $property->id);
        $this->assertDatabaseCount('transactions', 0);
        $this->actingAs($user)->getJson($this->url($proposal))->assertOk()
            ->assertJsonPath('proposal.preview.property.name', 'San Nicolás')
            ->assertJsonPath('proposal.preview.amount', '84.00')
            ->assertJsonPath('proposal.preview.currency', 'EUR')
            ->assertJsonPath('proposal.status', 'pending');

        $this->postJson($this->url($proposal, 'revise'), ['revision' => 1, 'amount' => '89.35'])
            ->assertOk()->assertJsonPath('proposal.revision', 2)->assertJsonPath('proposal.preview.amount', '89.35');
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertConflict();
        $this->assertDatabaseCount('transactions', 0);
        $receipt = $this->postJson($this->url($proposal, 'confirm'), ['revision' => 2])->assertOk()
            ->assertJsonPath('proposal.status', 'executed')->json('proposal.result');
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 2])->assertOk()
            ->assertJsonPath('proposal.result.transaction_id', $receipt['transaction_id']);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', ['id' => $receipt['transaction_id'], 'amount' => 89.35, 'direction' => 'expense', 'property_id' => $property->id]);
        $this->assertSame('/finance', $receipt['path']);
        $this->assertSame(1, DB::table('ai_run_steps')->where('run_id', $run->id)->where('status', 'executed')->count());
        Http::assertNothingSent();
    }

    public function test_proposal_payload_and_snapshot_are_encrypted_and_audit_contains_no_financial_text(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $proposal = $this->propose($portfolio, $user, $run, $property->id);
        $raw = DB::table('ai_action_proposals')->find($proposal->id);
        $this->assertStringNotContainsString('Fontanería', $raw->payload);
        $this->assertStringNotContainsString('84.00', $raw->payload);
        $this->assertStringNotContainsString('San Nicolás', $raw->property_snapshot);
        $this->assertArrayNotHasKey('payload', $proposal->toArray());
        $audit = DB::table('ai_run_steps')->where('run_id', $run->id)->pluck('metadata')->implode('');
        $this->assertStringNotContainsString('Fontanería', $audit);
        $this->assertStringNotContainsString('San Nicol', $audit);
    }

    public function test_repeated_tool_proposals_are_idempotent_but_different_payloads_conflict(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $first = $this->propose($portfolio, $user, $run, $property->id);
        $second = $this->propose($portfolio, $user, $run, $property->id);
        $this->assertSame($first->id, $second->id);
        try {
            $this->propose($portfolio, $user, $run, $property->id, ['amount' => '90.00']);
            $this->fail('Different payload must not reuse the existing proposal.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('ai_action_proposals', 1);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_another_user_even_in_the_same_portfolio_cannot_read_or_change_a_proposal(): void
    {
        [$owner, $portfolio, $property, $run] = $this->context();
        $proposal = $this->propose($portfolio, $owner, $run, $property->id);
        $other = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio->members()->attach($other, ['role' => 'owner']);
        $this->actingAs($other)->getJson($this->url($proposal))->assertNotFound();
        foreach (['revise', 'confirm', 'cancel'] as $operation) {
            $this->postJson($this->url($proposal, $operation), ['revision' => 1])->assertNotFound();
        }
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tool_cannot_target_a_property_outside_the_authenticated_portfolio(): void
    {
        [$user, $portfolio, , $run] = $this->context();
        [, , $foreign] = $this->context();
        try {
            $this->propose($portfolio, $user, $run, $foreign->id);
            $this->fail('Foreign property was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('ai_action_proposals', 0);
    }

    public function test_unknown_tool_fields_invalid_money_and_unimplemented_categories_are_rejected(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        foreach ([['confirmed' => true], ['portfolio_id' => $portfolio->id], ['amount' => '84.001'], ['amount' => '-84'], ['amount' => '1e3'], ['amount' => '0'], ['amount' => '10000000000'], ['amount' => 84], ['category' => 'rent'], ['status' => 'cancelled']] as $invalid) {
            try {
                $this->propose($portfolio, $user, $run, $property->id, $invalid);
                $this->fail('Invalid structured arguments were accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('ai_action_proposals', 0);
            }
        }
    }

    public function test_confirmation_payload_cannot_override_the_preview_or_change_the_property(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $proposal = $this->propose($portfolio, $user, $run, $property->id);
        $this->actingAs($user)->postJson($this->url($proposal, 'confirm'), ['revision' => 1, 'amount' => '1.00'])
            ->assertUnprocessable();
        $this->postJson($this->url($proposal, 'revise'), ['revision' => 1, 'property_id' => $property->id])
            ->assertUnprocessable();
        $this->postJson($this->url($proposal, 'confirm'), [])->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_cancel_is_idempotent_and_a_cancelled_proposal_cannot_be_confirmed(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $proposal = $this->propose($portfolio, $user, $run, $property->id);
        $this->actingAs($user)->postJson($this->url($proposal, 'cancel'), ['revision' => 1])->assertOk()
            ->assertJsonPath('proposal.status', 'cancelled');
        $this->postJson($this->url($proposal, 'cancel'), ['revision' => 1])->assertOk();
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertConflict();
        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame(1, DB::table('ai_run_steps')->where('run_id', $run->id)->where('status', 'cancelled')->count());
    }

    public function test_expired_proposal_is_persistently_expired_without_creating_an_expense(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $proposal = $this->propose($portfolio, $user, $run, $property->id);
        $this->travel(31)->minutes();
        $this->actingAs($user)->getJson($this->url($proposal))->assertOk()->assertJsonPath('proposal.status', 'expired');
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertConflict();
        $this->assertSame('expired', $proposal->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_revoked_consent_and_plan_changes_block_confirmation_but_allow_cancellation(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $proposal = $this->propose($portfolio, $user, $run, $property->id);
        $user->forceFill(['assistant_enabled_at' => null])->save();
        $this->actingAs($user)->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertForbidden();
        $user->forceFill(['assistant_enabled_at' => now()])->save();
        $portfolio->update(['plan' => 'free', 'trial_ends_at' => null]);
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertForbidden();
        $this->postJson($this->url($proposal, 'cancel'), ['revision' => 1])->assertOk();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_deleted_conversation_cannot_leave_a_confirmable_proposal(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $proposal = $this->propose($portfolio, $user, $run, $property->id);
        AiConversation::findOrFail($run->conversation_id)->delete();
        $this->actingAs($user)->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertConflict();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_property_changes_require_a_fresh_preview_and_confirmation(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $proposal = $this->propose($portfolio, $user, $run, $property->id);
        $property->update(['name' => 'San Nicolás reformado']);
        $this->actingAs($user)->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertConflict();
        $this->postJson($this->url($proposal, 'revise'), ['revision' => 1])->assertOk()
            ->assertJsonPath('proposal.revision', 2)->assertJsonPath('proposal.preview.property.name', 'San Nicolás reformado');
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 2])->assertOk();
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_property_plan_limits_are_checked_again_when_confirming(): void
    {
        [$user, $portfolio, , $run] = $this->context();
        $property = $portfolio->properties()->create(['name' => 'Segundo', 'type' => 'housing', 'address_line' => 'Calle 2']);
        $proposal = $this->propose($portfolio, $user, $run, $property->id);
        config(['plans.founder.property_limit' => 1]);
        $this->actingAs($user)->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_confirmation_works_without_an_ai_provider_call_or_configured_key(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $proposal = $this->propose($portfolio, $user, $run, $property->id);
        config(['services.openai.key' => null]);
        $this->actingAs($user)->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertOk();
        Http::assertNothingSent();
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_failed_audit_rolls_back_both_the_expense_and_the_receipt(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $proposal = $this->propose($portfolio, $user, $run, $property->id);
        DB::listen(function (QueryExecuted $query) {
            if (str_contains($query->sql, 'insert into "ai_run_steps"') && in_array('executed', $query->bindings, true)) {
                throw new \RuntimeException('Simulated audit failure');
            }
        });
        try {
            app(ActionProposalService::class)->confirm($portfolio, $user, $proposal->id, 1);
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('pending', $proposal->fresh()->status);
        $this->assertNull($proposal->fresh()->result);
    }

    public function test_manual_expenses_use_the_same_decimal_validation_and_preserve_general_expenses(): void
    {
        [$user] = $this->context();
        $input = ['direction' => 'expense', 'category' => 'maintenance', 'description' => 'Manual', 'amount' => '84.20', 'transaction_date' => today()->toDateString(), 'status' => 'paid'];
        $this->actingAs($user)->postJson('/api/v1/transactions', $input)->assertCreated()->assertJsonPath('amount', '84.20');
        $this->postJson('/api/v1/transactions', [...$input, 'amount' => '84.201'])->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 1);
    }

    private function context(): array
    {
        $user = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio = Portfolio::create(['name' => 'Cartera', 'currency' => 'EUR', 'plan' => 'founder', 'trial_ends_at' => null]);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'San Nicolás', 'type' => 'housing', 'address_line' => 'Calle 1']);
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        $run = AiRun::create([
            'portfolio_id' => $portfolio->id, 'user_id' => $user->id, 'conversation_id' => $conversation->id,
            'client_request_id' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'test expense'),
            'plan' => 'founder', 'routing_version' => 'alquivo-v1', 'billing_month' => now()->startOfMonth()->toDateString(),
        ]);

        return [$user, $portfolio, $property, $run];
    }

    private function propose(Portfolio $portfolio, User $user, AiRun $run, int $propertyId, array $overrides = []): AiActionProposal
    {
        return app(ActionProposalService::class)->proposeExpense($portfolio, $user, $run, [
            'property_id' => $propertyId, 'amount' => '84.00', 'category' => 'maintenance',
            'description' => 'Fontanería', 'transaction_date' => today()->toDateString(), 'status' => 'paid', ...$overrides,
        ]);
    }

    private function url(AiActionProposal $proposal, ?string $operation = null): string
    {
        return '/api/v1/assistant/proposals/'.$proposal->id.($operation ? '/'.$operation : '');
    }
}
