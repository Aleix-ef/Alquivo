<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiActionProposal;
use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Services\ActionProposalService;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AssistantAdditionalProposalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.enabled' => true, 'ai.actions.enabled' => true, 'beta.assistant_validated' => true]);
        Http::preventStrayRequests();
    }

    public function test_phone_preview_edit_and_explicit_confirmation_preserve_other_contact_fields(): void
    {
        [$user, $portfolio, , $run, $contact] = $this->context();
        $proposal = app(ActionProposalService::class)->proposeContactPhone($portfolio, $user, $run, [
            'contact_id' => $contact->id, 'phone' => '+34 612 345 678',
        ]);
        $this->assertSame('600123123', $contact->fresh()->phone);
        $this->actingAs($user)->getJson($this->url($proposal))->assertOk()
            ->assertJsonPath('proposal.type', 'contact_phone')->assertJsonPath('proposal.preview.contact.name', 'Ana Pérez')
            ->assertJsonPath('proposal.preview.previous_phone', '600123123');
        $this->postJson($this->url($proposal, 'revise'), ['revision' => 1, 'phone' => '+34 699 123 456'])
            ->assertOk()->assertJsonPath('proposal.revision', 2)->assertJsonPath('proposal.preview.phone', '+34 699 123 456');
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertConflict();
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 2])->assertOk()
            ->assertJsonPath('proposal.result.contact_id', $contact->id)->assertJsonPath('proposal.result.path', '/contacts');
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 2])->assertOk();
        $this->assertSame('+34 699 123 456', $contact->fresh()->phone);
        $this->assertSame('ana@example.test', $contact->fresh()->email);
        $this->assertSame('No cambiar esta nota', $contact->fresh()->notes);
        $this->assertSame(1, $this->executionCount($proposal));
        Http::assertNothingSent();
    }

    public function test_phone_changes_in_the_same_second_require_a_new_preview(): void
    {
        [$user, $portfolio, , $run, $contact] = $this->context();
        $proposal = app(ActionProposalService::class)->proposeContactPhone($portfolio, $user, $run, [
            'contact_id' => $contact->id, 'phone' => '612345678',
        ]);
        DB::table('contacts')->where('id', $contact->id)->update(['phone' => '677123456']);
        $this->actingAs($user)->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertConflict();
        $this->assertSame('677123456', $contact->fresh()->phone);
        $this->postJson($this->url($proposal, 'revise'), ['revision' => 1])->assertOk()
            ->assertJsonPath('proposal.preview.previous_phone', '677123456')->assertJsonPath('proposal.revision', 2);
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 2])->assertOk();
    }

    public function test_rent_payment_preview_partial_payment_and_replay_create_one_income_and_refresh_balance(): void
    {
        [$user, $portfolio, $property, $run, , $charge] = $this->context();
        $proposal = $this->paymentProposal($portfolio, $user, $run, $charge);
        $this->assertDatabaseCount('transactions', 0);
        $this->actingAs($user)->getJson($this->url($proposal))->assertOk()
            ->assertJsonPath('proposal.preview.rent_charge.remaining_amount', '800.00')
            ->assertJsonPath('proposal.preview.property.id', $property->id)
            ->assertJsonPath('proposal.preview.lease.id', $charge->lease_id);
        $this->postJson($this->url($proposal, 'revise'), ['revision' => 1, 'amount' => '84.35'])
            ->assertOk()->assertJsonPath('proposal.revision', 2)->assertJsonPath('proposal.preview.amount', '84.35');
        $receipt = $this->postJson($this->url($proposal, 'confirm'), ['revision' => 2])->assertOk()
            ->assertJsonPath('proposal.status', 'executed')->json('proposal.result');
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 2])->assertOk()
            ->assertJsonPath('proposal.result.transaction_id', $receipt['transaction_id']);
        $this->assertSame('/leases/'.$charge->lease_id, $receipt['path']);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'id' => $receipt['transaction_id'], 'amount' => '84.35', 'direction' => 'income', 'status' => 'paid',
            'property_id' => $property->id, 'rent_charge_id' => $charge->id, 'lease_id' => $charge->lease_id,
        ]);
        $this->assertSame('84.35', $charge->fresh()->paid_amount);
        $this->assertSame(1, $this->executionCount($proposal));
        Http::assertNothingSent();
    }

    public function test_an_external_payment_invalidates_preview_even_when_charge_cache_is_not_updated(): void
    {
        [$user, $portfolio, $property, $run, , $charge] = $this->context();
        $proposal = $this->paymentProposal($portfolio, $user, $run, $charge);
        $this->existingPayment($portfolio, $property->id, $charge, '100.00');
        $this->actingAs($user)->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertConflict();
        $this->assertDatabaseCount('transactions', 1);
        $this->postJson($this->url($proposal, 'revise'), ['revision' => 1])->assertOk()
            ->assertJsonPath('proposal.preview.rent_charge.remaining_amount', '700.00');
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 2])->assertOk();
        $this->assertSame('184.00', $charge->fresh()->paid_amount);
    }

    public function test_a_fully_paid_charge_cannot_be_paid_again_from_an_old_proposal(): void
    {
        [$user, $portfolio, $property, $run, , $charge] = $this->context();
        $proposal = $this->paymentProposal($portfolio, $user, $run, $charge);
        $this->existingPayment($portfolio, $property->id, $charge, '800.00');
        $this->actingAs($user)->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertConflict();
        $this->postJson($this->url($proposal, 'revise'), ['revision' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame('pending', $proposal->fresh()->status);
        $this->assertSame(0, $this->executionCount($proposal));
    }

    public function test_property_note_appends_without_replacing_existing_notes_or_duplication(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $proposal = app(ActionProposalService::class)->proposePropertyNote($portfolio, $user, $run, [
            'property_id' => $property->id, 'note' => 'Revisar la caldera en octubre.',
        ]);
        $this->assertSame('Nota anterior.', $property->fresh()->notes);
        $this->actingAs($user)->postJson($this->url($proposal, 'revise'), ['revision' => 1, 'note' => 'Revisar la caldera en noviembre.'])
            ->assertOk()->assertJsonPath('proposal.preview.note', 'Revisar la caldera en noviembre.');
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 2])->assertOk()
            ->assertJsonPath('proposal.result.property_id', $property->id)->assertJsonPath('proposal.result.path', '/properties/'.$property->id);
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 2])->assertOk();
        $notes = $property->fresh()->notes;
        $this->assertStringStartsWith('Nota anterior.', $notes);
        $this->assertStringContainsString('Revisar la caldera en noviembre.', $notes);
        $this->assertSame(1, substr_count($notes, 'Revisar la caldera'));
        $this->assertSame(1, $this->executionCount($proposal));
    }

    public function test_note_content_changes_without_timestamp_changes_block_execution_until_reviewed(): void
    {
        [$user, $portfolio, $property, $run] = $this->context();
        $proposal = app(ActionProposalService::class)->proposePropertyNote($portfolio, $user, $run, [
            'property_id' => $property->id, 'note' => 'Nueva nota.',
        ]);
        DB::table('properties')->where('id', $property->id)->update(['notes' => 'Editado desde el formulario.']);
        $this->actingAs($user)->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertConflict();
        $this->postJson($this->url($proposal, 'revise'), ['revision' => 1])->assertOk();
        $this->postJson($this->url($proposal, 'confirm'), ['revision' => 2])->assertOk();
        $this->assertStringStartsWith('Editado desde el formulario.', $property->fresh()->notes);
        $this->assertStringContainsString('Nueva nota.', $property->fresh()->notes);
    }

    public function test_strict_typed_editing_disallows_target_changes_or_fields_of_another_action(): void
    {
        [$user, $portfolio, $property, $run, $contact, $charge] = $this->context();
        $service = app(ActionProposalService::class);
        $proposals = [
            [$service->proposeContactPhone($portfolio, $user, $run, ['contact_id' => $contact->id, 'phone' => '612345678']), 'contact_id', $contact->id, 'note'],
            [$this->paymentProposal($portfolio, $user, $run, $charge), 'rent_charge_id', $charge->id, 'phone'],
            [$service->proposePropertyNote($portfolio, $user, $run, ['property_id' => $property->id, 'note' => 'Nueva nota.']), 'property_id', $property->id, 'amount'],
        ];
        $this->actingAs($user);
        foreach ($proposals as [$proposal, $targetKey, $targetId, $invalidField]) {
            $this->postJson($this->url($proposal, 'revise'), ['revision' => 1, $targetKey => $targetId])->assertUnprocessable();
            $this->postJson($this->url($proposal, 'revise'), ['revision' => 1, $invalidField => '84.00'])->assertUnprocessable();
            $this->postJson($this->url($proposal, 'confirm'), ['revision' => 1, 'confirmed' => true])->assertUnprocessable();
            $this->assertSame(1, $proposal->fresh()->revision);
        }
        $this->assertSame('600123123', $contact->fresh()->phone);
        $this->assertSame('Nota anterior.', $property->fresh()->notes);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_all_new_proposals_are_private_to_actor_and_cancellation_does_not_change_entities(): void
    {
        [$user, $portfolio, $property, $run, $contact, $charge] = $this->context();
        $service = app(ActionProposalService::class);
        $proposals = [
            $service->proposeContactPhone($portfolio, $user, $run, ['contact_id' => $contact->id, 'phone' => '612345678']),
            $this->paymentProposal($portfolio, $user, $run, $charge),
            $service->proposePropertyNote($portfolio, $user, $run, ['property_id' => $property->id, 'note' => 'Nueva nota.']),
        ];
        $other = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio->members()->attach($other, ['role' => 'owner']);
        foreach ($proposals as $proposal) {
            $this->actingAs($other)->getJson($this->url($proposal))->assertNotFound();
            foreach (['revise', 'confirm', 'cancel'] as $operation) {
                $this->postJson($this->url($proposal, $operation), ['revision' => 1])->assertNotFound();
            }
            $this->actingAs($user)->postJson($this->url($proposal, 'cancel'), ['revision' => 1])->assertOk();
            $this->postJson($this->url($proposal, 'cancel'), ['revision' => 1])->assertOk();
            $this->postJson($this->url($proposal, 'confirm'), ['revision' => 1])->assertConflict();
        }
        $this->assertSame('600123123', $contact->fresh()->phone);
        $this->assertSame('Nota anterior.', $property->fresh()->notes);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_foreign_contacts_charges_and_properties_are_never_accepted(): void
    {
        [$user, $portfolio, , $run] = $this->context();
        [, , $foreignProperty, , $foreignContact, $foreignCharge] = $this->context();
        $service = app(ActionProposalService::class);
        foreach ([
            fn () => $service->proposeContactPhone($portfolio, $user, $run, ['contact_id' => $foreignContact->id, 'phone' => '612345678']),
            fn () => $this->paymentProposal($portfolio, $user, $run, $foreignCharge),
            fn () => $service->proposePropertyNote($portfolio, $user, $run, ['property_id' => $foreignProperty->id, 'note' => 'Nueva nota.']),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A foreign target was accepted.');
            } catch (ModelNotFoundException) {
                $this->assertDatabaseCount('ai_action_proposals', 0);
            } catch (HttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }
        $this->assertDatabaseCount('ai_action_proposals', 0);
    }

    public function test_new_payloads_reject_phone_deletion_overpayment_and_note_overflow(): void
    {
        [$user, $portfolio, $property, $run, $contact, $charge] = $this->context();
        $service = app(ActionProposalService::class);
        foreach ([
            fn () => $service->proposeContactPhone($portfolio, $user, $run, ['contact_id' => $contact->id, 'phone' => null]),
            fn () => $service->proposeContactPhone($portfolio, $user, $run, ['contact_id' => $contact->id, 'phone' => 'call me']),
            fn () => $this->paymentProposal($portfolio, $user, $run, $charge, ['amount' => '800.01']),
            fn () => $this->paymentProposal($portfolio, $user, $run, $charge, ['amount' => 84]),
            fn () => $service->proposePropertyNote($portfolio, $user, $run, ['property_id' => $property->id, 'note' => str_repeat('a', 2001)]),
            fn () => $service->proposePropertyNote($portfolio, $user, $run, ['property_id' => $property->id, 'note' => 'Nueva', 'replace' => true]),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Invalid action was accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('ai_action_proposals', 0);
            }
        }
        $property->update(['notes' => str_repeat('a', 9999)]);
        $this->expectException(ValidationException::class);
        $service->proposePropertyNote($portfolio, $user, $run, ['property_id' => $property->id, 'note' => 'Nueva']);
    }

    public function test_new_proposals_encrypt_phone_and_notes_and_only_audit_identifiers(): void
    {
        [$user, $portfolio, $property, $run, $contact] = $this->context();
        $service = app(ActionProposalService::class);
        $phone = $service->proposeContactPhone($portfolio, $user, $run, ['contact_id' => $contact->id, 'phone' => '612345678']);
        $note = $service->proposePropertyNote($portfolio, $user, $run, ['property_id' => $property->id, 'note' => 'Una nota muy privada']);
        foreach ([$phone, $note] as $proposal) {
            $raw = DB::table('ai_action_proposals')->find($proposal->id);
            foreach (['612345678', '600123123', 'Ana Pérez', 'Una nota muy privada'] as $private) {
                $this->assertStringNotContainsString($private, $raw->payload.$raw->property_snapshot);
            }
            $service->confirm($portfolio, $user, $proposal->id, 1);
        }
        $audit = DB::table('ai_run_steps')->where('run_id', $run->id)->pluck('metadata')->implode('');
        foreach (['612345678', '600123123', 'Ana', 'muy privada'] as $private) {
            $this->assertStringNotContainsString($private, $audit);
        }
    }

    public function test_failed_execution_audit_rolls_back_phone_and_proposal_receipt(): void
    {
        [$user, $portfolio, , $run, $contact] = $this->context();
        $service = app(ActionProposalService::class);
        $proposal = $service->proposeContactPhone($portfolio, $user, $run, ['contact_id' => $contact->id, 'phone' => '612345678']);
        DB::listen(function (QueryExecuted $query) {
            if (str_contains($query->sql, 'insert into "ai_run_steps"') && in_array('executed', $query->bindings, true)) {
                throw new \RuntimeException('Simulated action audit failure');
            }
        });
        try {
            $service->confirm($portfolio, $user, $proposal->id, 1);
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated action audit failure', $exception->getMessage());
        }
        $this->assertSame('600123123', $contact->fresh()->phone);
        $this->assertSame('pending', $proposal->fresh()->status);
        $this->assertNull($proposal->fresh()->result);
    }

    public function test_payment_contract_changes_and_deleted_contacts_block_pending_confirmation(): void
    {
        [$user, $portfolio, , $run, $contact, $charge] = $this->context();
        $phone = app(ActionProposalService::class)->proposeContactPhone($portfolio, $user, $run, ['contact_id' => $contact->id, 'phone' => '612345678']);
        $payment = $this->paymentProposal($portfolio, $user, $run, $charge);
        $contact->delete();
        DB::table('leases')->where('id', $charge->lease_id)->update(['status' => 'draft']);
        $this->actingAs($user)->postJson($this->url($phone, 'confirm'), ['revision' => 1])->assertNotFound();
        $this->postJson($this->url($payment, 'confirm'), ['revision' => 1])->assertConflict();
        $this->assertDatabaseCount('transactions', 0);
    }

    private function context(): array
    {
        $user = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio = Portfolio::create(['name' => 'Cartera', 'currency' => 'EUR', 'plan' => 'founder', 'trial_ends_at' => null]);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create([
            'name' => 'San Nicolás', 'type' => 'housing', 'address_line' => 'Calle 1', 'notes' => 'Nota anterior.',
        ]);
        $contact = Contact::create([
            'portfolio_id' => $portfolio->id, 'name' => 'Ana Pérez', 'phone' => '600123123',
            'email' => 'ana@example.test', 'notes' => 'No cambiar esta nota',
        ]);
        $lease = Lease::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active',
            'start_date' => today()->subYear()->toDateString(), 'monthly_rent' => '800.00',
        ]);
        $lease->participants()->attach($contact, ['role' => 'tenant', 'is_primary' => true]);
        $charge = RentCharge::create([
            'portfolio_id' => $portfolio->id, 'lease_id' => $lease->id, 'period' => today()->format('Y-m'),
            'due_date' => today()->toDateString(), 'amount' => '800.00', 'paid_amount' => '0.00', 'status' => 'pending',
        ]);
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        $run = AiRun::create([
            'portfolio_id' => $portfolio->id, 'user_id' => $user->id, 'conversation_id' => $conversation->id,
            'client_request_id' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'test additional actions'),
            'plan' => 'founder', 'routing_version' => 'alquivo-v1', 'billing_month' => now()->startOfMonth()->toDateString(),
        ]);

        return [$user, $portfolio, $property, $run, $contact, $charge];
    }

    private function paymentProposal(Portfolio $portfolio, User $user, AiRun $run, RentCharge $charge, array $changes = []): AiActionProposal
    {
        return app(ActionProposalService::class)->proposeRentPayment($portfolio, $user, $run, [
            'rent_charge_id' => $charge->id, 'amount' => '84.00', 'transaction_date' => today()->toDateString(),
            'payment_method' => null, ...$changes,
        ]);
    }

    private function existingPayment(Portfolio $portfolio, int $propertyId, RentCharge $charge, string $amount): Transaction
    {
        return Transaction::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $propertyId, 'lease_id' => $charge->lease_id,
            'rent_charge_id' => $charge->id, 'amount' => $amount, 'direction' => 'income', 'category' => 'rent',
            'description' => 'Cobro manual', 'status' => 'paid', 'transaction_date' => today()->toDateString(),
        ]);
    }

    private function executionCount(AiActionProposal $proposal): int
    {
        return DB::table('ai_run_steps')->where('run_id', $proposal->run_id)->where('status', 'executed')
            ->where('tool', 'propose_'.$proposal->type)->count();
    }

    private function url(AiActionProposal $proposal, ?string $operation = null): string
    {
        return '/api/v1/assistant/proposals/'.$proposal->id.($operation ? '/'.$operation : '');
    }
}
