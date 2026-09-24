<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\RecordRentPayment;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Actions\UpdateContactPhone;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Properties\Actions\AppendPropertyNote;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AssistantDomainActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_phone_update_changes_only_the_selected_contact_and_preserves_the_format(): void
    {
        [$user, $portfolio, , $contact] = $this->context();
        $other = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'Otro', 'kind' => 'person', 'phone' => '600111222']);
        $updated = app(UpdateContactPhone::class)->execute($portfolio, $user, ['contact_id' => $contact->id, 'phone' => ' +34 (612) 345-678 ']);

        $this->assertSame('+34 (612) 345-678', $updated->phone);
        $this->assertSame('600111222', $other->fresh()->phone);
        $this->assertSame('María', $updated->name);
        Http::assertNothingSent();
    }

    public function test_phone_rejects_letters_bad_length_and_missing_target(): void
    {
        [$user, $portfolio, , $contact] = $this->context();
        foreach (['123', '1234567890123456', '+34 612 CALLME', '++34 612345678', '  '] as $phone) {
            $this->assertInvalid(fn () => app(UpdateContactPhone::class)->execute($portfolio, $user, ['contact_id' => $contact->id, 'phone' => $phone]));
        }
        $this->assertInvalid(fn () => app(UpdateContactPhone::class)->execute($portfolio, $user, ['phone' => '612345678']));
        $this->assertSame('600000001', $contact->fresh()->phone);
    }

    public function test_phone_manual_update_is_atomic_with_other_fields_and_supports_explicit_clear(): void
    {
        [$user, , , $contact] = $this->context();
        $this->actingAs($user)->putJson('/api/v1/contacts/'.$contact->id, ['name' => 'No cambiar', 'phone' => 'not-a-phone'])
            ->assertUnprocessable();
        $this->assertSame('María', $contact->fresh()->name);
        $this->putJson('/api/v1/contacts/'.$contact->id, ['phone' => '+34 612 345 678'])
            ->assertOk()->assertJsonPath('phone', '+34 612 345 678');
        $this->putJson('/api/v1/contacts/'.$contact->id, ['phone' => null])->assertOk()->assertJsonPath('phone', null);
    }

    public function test_contact_actions_are_isolated_by_portfolio_owner_and_soft_delete(): void
    {
        [$user, $portfolio, , $contact] = $this->context();
        [$stranger, , , $foreign] = $this->context();
        $action = app(UpdateContactPhone::class);
        $this->assertNotFound(fn () => $action->execute($portfolio, $user, ['contact_id' => $foreign->id, 'phone' => '612345678']));
        $this->assertNotFound(fn () => $action->execute($portfolio, $stranger, ['contact_id' => $contact->id, 'phone' => '612345678']));
        $portfolio->members()->attach($stranger, ['role' => 'viewer']);
        $this->assertNotFound(fn () => $action->execute($portfolio, $stranger, ['contact_id' => $contact->id, 'phone' => '612345678']));
        $contact->delete();
        $this->assertNotFound(fn () => $action->execute($portfolio, $user, ['contact_id' => $contact->id, 'phone' => '612345678']));
    }

    public function test_appended_property_notes_preserve_existing_text_and_enforce_both_limits(): void
    {
        [$user, $portfolio, $property] = $this->context();
        $original = "Nota antigua \n";
        $property->update(['notes' => $original]);
        $action = app(AppendPropertyNote::class);
        $updated = $action->execute($portfolio, $user, ['property_id' => $property->id, 'note' => ' Revisar persiana ']);
        $this->assertSame($original."\n\nRevisar persiana", $updated->notes);
        foreach ([' ', str_repeat('n', 2001)] as $note) {
            $this->assertInvalid(fn () => $action->execute($portfolio, $user, ['property_id' => $property->id, 'note' => $note]));
        }
        $property->update(['notes' => str_repeat('á', 7998)]);
        $this->assertSame(10000, mb_strlen($action->execute($portfolio, $user, ['property_id' => $property->id, 'note' => str_repeat('ñ', 2000)])->notes));
        $this->assertInvalid(fn () => $action->execute($portfolio, $user, ['property_id' => $property->id, 'note' => 'Otra']));
        $this->assertSame(10000, mb_strlen($property->fresh()->notes));
    }

    public function test_property_note_authorization_rejects_foreign_archived_and_read_only_properties(): void
    {
        [$user, $portfolio, $property] = $this->context();
        [$stranger, , $foreign] = $this->context();
        $action = app(AppendPropertyNote::class);
        $this->assertNotFound(fn () => $action->execute($portfolio, $stranger, ['property_id' => $property->id, 'note' => 'Nota']));
        $this->assertNotFound(fn () => $action->execute($portfolio, $user, ['property_id' => $foreign->id, 'note' => 'Nota']));
        $extra = $portfolio->properties()->create(['name' => 'Segundo', 'type' => 'housing', 'address_line' => 'Calle 2']);
        config(['plans.founder.property_limit' => 1]);
        $this->assertInvalid(fn () => $action->execute($portfolio, $user, ['property_id' => $extra->id, 'note' => 'Nota']));
        $property->delete();
        $this->assertNotFound(fn () => $action->execute($portfolio, $user, ['property_id' => $property->id, 'note' => 'Nota']));
    }

    public function test_rent_payment_keeps_charge_identity_and_exact_partial_and_full_balances(): void
    {
        [$user, $portfolio, $property, , $charge] = $this->context('0.30');
        $action = app(RecordRentPayment::class);
        $first = $action->execute($portfolio, $user, $this->payment($charge, '0.10'));
        $this->assertSame('partial', $charge->fresh()->status);
        $this->assertSame('0.10', $charge->fresh()->paid_amount);
        $second = $action->execute($portfolio, $user, $this->payment($charge, '0.20'));
        $this->assertSame('paid', $charge->fresh()->status);
        $this->assertSame('0.30', $charge->fresh()->paid_amount);
        foreach ([$first, $second] as $payment) {
            $this->assertSame($charge->id, $payment->rent_charge_id);
            $this->assertSame($charge->lease_id, $payment->lease_id);
            $this->assertSame($property->id, $payment->property_id);
            $this->assertSame('rent', $payment->category);
            $this->assertSame('income', $payment->direction);
        }
        $this->assertDatabaseCount('rent_charges', 1);
        $this->assertInvalid(fn () => $action->execute($portfolio, $user, $this->payment($charge, '0.01')));
        $this->assertDatabaseCount('transactions', 2);
        Http::assertNothingSent();
    }

    public function test_payment_uses_actual_transactions_not_stale_cached_paid_amount(): void
    {
        [$user, $portfolio, , , $charge] = $this->context('900.00');
        $action = app(RecordRentPayment::class);
        $action->execute($portfolio, $user, $this->payment($charge, '899.99'));
        $charge->update(['paid_amount' => '0.00', 'status' => 'pending']);
        $this->assertInvalid(fn () => $action->execute($portfolio, $user, $this->payment($charge, '0.02')));
        $action->execute($portfolio, $user, $this->payment($charge, '0.01'));
        $this->assertSame('900.00', $charge->fresh()->paid_amount);
        $this->assertSame('paid', $charge->fresh()->status);
    }

    public function test_payment_decimal_dates_and_metadata_validation_fail_closed(): void
    {
        [$user, $portfolio, , , $charge] = $this->context('9999999999.99');
        $action = app(RecordRentPayment::class);
        foreach (['0', '-1', '0.001', '1e3', '10000000000', 'NaN', '1,23'] as $amount) {
            $this->assertInvalid(fn () => $action->execute($portfolio, $user, $this->payment($charge, $amount)));
        }
        foreach ([['transaction_date' => '2026-02-30'], ['transaction_date' => 'tomorrow'], ['payment_method' => str_repeat('x', 41)], ['notes' => str_repeat('n', 1001)]] as $invalid) {
            $this->assertInvalid(fn () => $action->execute($portfolio, $user, [...$this->payment($charge), ...$invalid]));
        }
        $this->assertDatabaseCount('transactions', 0);
        $payment = $action->execute($portfolio, $user, $this->payment($charge, '9999999999.99'));
        $this->assertSame('9999999999.99', $payment->amount);
        $this->assertSame('paid', $charge->fresh()->status);
    }

    public function test_payment_owner_portfolio_and_soft_deleted_lease_are_revalidated(): void
    {
        [$user, $portfolio, , , $charge] = $this->context();
        [$stranger, , , , $foreign] = $this->context();
        $action = app(RecordRentPayment::class);
        $this->assertNotFound(fn () => $action->execute($portfolio, $stranger, $this->payment($charge)));
        $portfolio->members()->attach($stranger, ['role' => 'viewer']);
        $this->assertNotFound(fn () => $action->execute($portfolio, $stranger, $this->payment($charge)));
        $this->assertNotFound(fn () => $action->execute($portfolio, $user, $this->payment($foreign)));
        $charge->lease->delete();
        $this->assertNotFound(fn () => $action->execute($portfolio, $user, $this->payment($charge)));
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_payment_rejects_cancelled_contract_archived_property_and_property_limit(): void
    {
        [$user, $portfolio, $property, , $charge] = $this->context();
        $action = app(RecordRentPayment::class);
        foreach (['cancelled', 'draft'] as $status) {
            $charge->lease->update(['status' => $status]);
            $this->assertInvalid(fn () => $action->execute($portfolio, $user, $this->payment($charge)));
        }
        $charge->lease->update(['status' => 'ended']);
        $action->execute($portfolio, $user, $this->payment($charge));
        config(['plans.founder.property_limit' => 0]);
        $this->assertInvalid(fn () => $action->execute($portfolio, $user, $this->payment($charge)));
        config(['plans.founder.property_limit' => 10]);
        $property->delete();
        $this->assertNotFound(fn () => $action->execute($portfolio, $user, $this->payment($charge)));
    }

    public function test_manual_payment_update_and_delete_use_decimal_balance_and_current_entitlements(): void
    {
        [$user, $portfolio, $property, , $charge] = $this->context('0.30');
        $this->actingAs($user);
        $first = $this->postJson('/api/v1/rent-charges/'.$charge->id.'/payments', $this->payment($charge, '0.10'))
            ->assertCreated()->json('id');
        $second = $this->postJson('/api/v1/rent-charges/'.$charge->id.'/payments', $this->payment($charge, '0.20'))
            ->assertCreated()->json('id');
        $this->putJson('/api/v1/rent-payments/'.$first, $this->payment($charge, '0.11'))->assertUnprocessable();
        $this->putJson('/api/v1/rent-payments/'.$first, $this->payment($charge, '0.09'))
            ->assertOk()->assertJsonPath('amount', '0.09');
        $this->assertSame('0.29', $charge->fresh()->paid_amount);
        $this->assertSame('partial', $charge->fresh()->status);
        $this->deleteJson('/api/v1/rent-payments/'.$second)->assertNoContent();
        $this->assertSame('0.09', $charge->fresh()->paid_amount);
        $this->deleteJson('/api/v1/rent-payments/'.$first)->assertNoContent();
        $this->assertSame('0.00', $charge->fresh()->paid_amount);
        $this->assertSame('pending', $charge->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_payment_mutation_routes_cannot_modify_other_portfolios_or_generic_income(): void
    {
        [$user, $portfolio, $property, , $charge] = $this->context();
        [$other, $otherPortfolio, , , $otherCharge] = $this->context();
        $foreign = app(RecordRentPayment::class)->execute($otherPortfolio, $other, $this->payment($otherCharge));
        $generic = Transaction::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id,
            'direction' => 'income', 'category' => 'other', 'description' => 'Otro', 'amount' => '10.00',
            'transaction_date' => today(), 'status' => 'paid']);
        $this->actingAs($user)->putJson('/api/v1/rent-payments/'.$foreign->id, $this->payment($charge))->assertNotFound();
        $this->deleteJson('/api/v1/rent-payments/'.$foreign->id)->assertNotFound();
        $this->putJson('/api/v1/rent-payments/'.$generic->id, $this->payment($charge))->assertUnprocessable();
        $this->deleteJson('/api/v1/rent-payments/'.$generic->id)->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_historical_payments_remain_correctable_after_contract_cancellation_or_draft_but_new_payments_are_blocked(): void
    {
        foreach (['cancelled', 'draft'] as $status) {
            [$user, $portfolio, , , $charge] = $this->context();
            $this->actingAs($user);
            $paymentId = $this->postJson('/api/v1/rent-charges/'.$charge->id.'/payments', $this->payment($charge, '100.00'))
                ->assertCreated()->json('id');
            $this->putJson('/api/v1/leases/'.$charge->lease_id, ['status' => $status])->assertOk();

            $this->postJson('/api/v1/rent-charges/'.$charge->id.'/payments', $this->payment($charge, '50.00'))->assertUnprocessable();
            $this->assertInvalid(fn () => app(RecordRentPayment::class)->validatedData($portfolio, $user, $this->payment($charge)));
            $this->putJson('/api/v1/rent-payments/'.$paymentId, $this->payment($charge, '90.25'))
                ->assertOk()->assertJsonPath('amount', '90.25');
            $this->assertSame('90.25', $charge->fresh()->paid_amount);
            $this->putJson('/api/v1/rent-payments/'.$paymentId, $this->payment($charge, '900.01'))->assertUnprocessable();
            $this->deleteJson('/api/v1/rent-payments/'.$paymentId)->assertNoContent();
            $this->assertSame('0.00', $charge->fresh()->paid_amount);
            $this->assertSame($status, $charge->fresh()->lease->status);
        }
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_cancelled_charge_stays_cancelled_when_historical_receipts_are_corrected_or_deleted(): void
    {
        [$user, $portfolio, $property, , $charge] = $this->context();
        $action = app(RecordRentPayment::class);
        $payment = $action->execute($portfolio, $user, $this->payment($charge, '100.00'));
        $charge->update(['status' => 'cancelled']);
        $this->assertInvalid(fn () => $action->execute($portfolio, $user, $this->payment($charge)));
        $this->assertInvalid(fn () => $action->validatedData($portfolio, $user, $this->payment($charge)));

        $this->actingAs($user)->putJson('/api/v1/rent-payments/'.$payment->id, $this->payment($charge, '80.00'))->assertOk();
        $this->assertSame('80.00', $charge->fresh()->paid_amount);
        $this->assertSame('cancelled', $charge->fresh()->status);

        config(['plans.founder.property_limit' => 0]);
        $this->deleteJson('/api/v1/rent-payments/'.$payment->id)->assertUnprocessable();
        config(['plans.founder.property_limit' => 10]);
        $property->delete();
        $this->deleteJson('/api/v1/rent-payments/'.$payment->id)->assertNotFound();
        $property->restore();

        $this->deleteJson('/api/v1/rent-payments/'.$payment->id)->assertNoContent();
        $this->assertSame('0.00', $charge->fresh()->paid_amount);
        $this->assertSame('cancelled', $charge->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
    }

    private function context(string $amount = '900.00'): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Patrimonio', 'plan' => 'founder', 'currency' => 'EUR']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'San Nicolás', 'type' => 'housing', 'address_line' => 'Calle 1']);
        $contact = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'María', 'kind' => 'person', 'phone' => '600000001']);
        $lease = Lease::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id,
            'status' => 'active', 'start_date' => today()->startOfMonth(), 'monthly_rent' => $amount, 'payment_day' => 5]);
        $lease->participants()->attach($contact, ['role' => 'tenant', 'is_primary' => true]);
        $charge = RentCharge::create(['portfolio_id' => $portfolio->id, 'lease_id' => $lease->id,
            'period' => today()->format('Y-m'), 'due_date' => today(), 'amount' => $amount, 'paid_amount' => '0.00', 'status' => 'pending']);

        return [$user, $portfolio, $property, $contact, $charge];
    }

    private function payment(RentCharge $charge, string $amount = '10.00'): array
    {
        return ['rent_charge_id' => $charge->id, 'amount' => $amount, 'transaction_date' => today()->toDateString(), 'payment_method' => 'transfer'];
    }

    private function assertInvalid(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The domain action accepted invalid input.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function assertNotFound(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The domain action accepted an inaccessible resource.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }
}
