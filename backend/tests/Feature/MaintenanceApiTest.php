<?php

namespace Tests\Feature;

use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\RecurringRule;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create([
            'name' => 'Piso', 'type' => 'housing', 'address_line' => 'Calle 1',
        ]);

        return [$user, $portfolio, $property];
    }

    public function test_owner_can_update_and_delete_a_document_and_its_private_file(): void
    {
        Storage::fake('local');
        [$user, , $property] = $this->context();
        $response = $this->actingAs($user)->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('seguro.pdf', 40, 'application/pdf'),
            'name' => 'Seguro antiguo', 'category' => 'insurance', 'property_id' => $property->id,
        ])->assertCreated();
        $document = Document::findOrFail($response->json('id'));

        $this->actingAs($user)->putJson("/api/v1/documents/{$document->id}", [
            'name' => 'Seguro 2026', 'category' => 'insurance', 'property_id' => null,
        ])->assertOk()->assertJsonPath('name', 'Seguro 2026')->assertJsonPath('property_id', null);

        $this->actingAs($user)->deleteJson("/api/v1/documents/{$document->id}")->assertNoContent();
        Storage::disk('local')->assertMissing($document->storage_key);
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    public function test_contact_can_only_be_archived_when_it_has_no_lease_history(): void
    {
        [$user, $portfolio, $property] = $this->context();
        $free = Contact::create(['portfolio_id' => $portfolio->id, 'kind' => 'person', 'name' => 'Libre']);
        $linked = Contact::create(['portfolio_id' => $portfolio->id, 'kind' => 'person', 'name' => 'Inquilino']);
        $lease = Lease::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active',
            'start_date' => today(), 'monthly_rent' => 700, 'payment_day' => 1,
        ]);
        $lease->participants()->attach($linked, ['role' => 'tenant', 'is_primary' => true]);

        $this->actingAs($user)->putJson("/api/v1/contacts/{$free->id}", ['phone' => '600123123'])
            ->assertOk()->assertJsonPath('phone', '600123123');
        $this->actingAs($user)->deleteJson("/api/v1/contacts/{$linked->id}")->assertUnprocessable();
        $this->actingAs($user)->deleteJson("/api/v1/contacts/{$free->id}")->assertNoContent();
        $this->assertSoftDeleted($free);
    }

    public function test_manual_transaction_is_editable_but_generated_finance_keeps_its_source_data(): void
    {
        [$user, $portfolio, $property] = $this->context();
        $manual = Transaction::create([
            'portfolio_id' => $portfolio->id, 'direction' => 'expense', 'category' => 'other',
            'description' => 'Estimación', 'amount' => 10, 'transaction_date' => today(), 'status' => 'pending',
        ]);
        $rule = RecurringRule::create([
            'portfolio_id' => $portfolio->id, 'direction' => 'expense', 'category' => 'insurance',
            'description' => 'Seguro', 'amount' => 30, 'frequency' => 'monthly', 'starts_on' => today(),
            'next_date' => today()->addMonth(), 'active' => true,
        ]);
        $generated = Transaction::create([
            'portfolio_id' => $portfolio->id, 'recurring_rule_id' => $rule->id, 'direction' => 'expense',
            'category' => 'insurance', 'description' => 'Seguro', 'amount' => 30,
            'transaction_date' => today(), 'status' => 'pending',
        ]);

        $this->actingAs($user)->putJson("/api/v1/transactions/{$manual->id}", [
            'property_id' => $property->id, 'description' => 'Reparación', 'amount' => 75,
        ])->assertOk()->assertJsonPath('description', 'Reparación')->assertJsonPath('amount', '75.00');

        $this->actingAs($user)->putJson("/api/v1/transactions/{$generated->id}", [
            'description' => 'Alterado', 'amount' => 999, 'status' => 'cancelled',
        ])->assertOk()->assertJsonPath('description', 'Seguro')->assertJsonPath('status', 'cancelled');
    }
}
