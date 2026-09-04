<?php

namespace Tests\Feature;

use App\Domain\Attention\Models\Issue;
use App\Domain\Attention\Models\Reminder;
use App\Domain\Documents\Models\Document;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttentionApiTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Piso', 'type' => 'housing', 'address_line' => 'Calle 1']);

        return [$user, $portfolio, $property];
    }

    public function test_document_is_stored_privately_and_without_exposing_its_storage_key(): void
    {
        Storage::fake('local');
        [$user, $portfolio, $property] = $this->context();

        $response = $this->actingAs($user)->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
            'name' => 'Contrato', 'category' => 'contract', 'property_id' => $property->id,
        ])->assertCreated()->assertJsonMissingPath('storage_key');

        $document = Document::find($response->json('id'));
        Storage::disk('local')->assertExists($document->storage_key);
        $this->assertSame($portfolio->id, $document->portfolio_id);
    }

    public function test_issue_and_reminder_reject_properties_from_another_portfolio(): void
    {
        [$user] = $this->context();
        $other = Portfolio::create(['name' => 'Otra']);
        $property = $other->properties()->create(['name' => 'Ajena', 'type' => 'housing', 'address_line' => 'Otra']);

        $this->actingAs($user)->postJson('/api/v1/issues', [
            'property_id' => $property->id, 'title' => 'Humedad', 'priority' => 'high',
            'reported_at' => today()->toDateString(),
        ])->assertNotFound();

        $this->actingAs($user)->postJson('/api/v1/reminders', [
            'property_id' => $property->id, 'title' => 'Revisar', 'starts_at' => now()->toDateTimeString(),
        ])->assertNotFound();
    }

    public function test_issues_can_be_filtered_by_property_without_exposing_another_portfolio(): void
    {
        [$user, $portfolio, $property] = $this->context();
        $secondProperty = $portfolio->properties()->create(['name' => 'Local', 'type' => 'commercial', 'address_line' => 'Calle 2']);
        foreach ([$property, $secondProperty] as $item) {
            Issue::create(['portfolio_id' => $portfolio->id, 'property_id' => $item->id, 'title' => 'Revisar', 'priority' => 'low', 'status' => 'open', 'reported_at' => today()]);
        }
        $this->actingAs($user)->getJson("/api/v1/issues?property_id={$property->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.property_id', $property->id);

        [$otherUser] = $this->context();
        $this->actingAs($otherUser)->getJson("/api/v1/issues?property_id={$property->id}")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_issue_can_be_assigned_and_resolved_with_one_financial_expense(): void
    {
        [$user, $portfolio, $property] = $this->context();
        $provider = Contact::create([
            'portfolio_id' => $portfolio->id, 'kind' => 'company', 'name' => 'Reparaciones Levante',
        ]);
        $issue = Issue::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id,
            'assigned_contact_id' => $provider->id, 'title' => 'Cambiar termo', 'priority' => 'high',
            'status' => 'in_progress', 'reported_at' => today(), 'estimated_cost' => 300,
        ]);
        $payload = [
            'status' => 'resolved', 'actual_cost' => 285, 'create_expense' => true,
            'expense_status' => 'paid', 'expense_date' => today()->toDateString(),
        ];

        $response = $this->actingAs($user)->putJson("/api/v1/issues/{$issue->id}", $payload)
            ->assertOk()->assertJsonPath('status', 'resolved')
            ->assertJsonPath('assigned_contact.name', 'Reparaciones Levante')
            ->assertJsonPath('expense_transaction.amount', '285.00');

        $this->actingAs($user)->putJson("/api/v1/issues/{$issue->id}", $payload)
            ->assertOk()->assertJsonPath('expense_transaction.id', $response->json('expense_transaction.id'));
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'id' => $response->json('expense_transaction.id'), 'category' => 'maintenance', 'amount' => 285,
        ]);
    }

    public function test_issue_rejects_a_provider_from_another_portfolio(): void
    {
        [$user, , $property] = $this->context();
        $other = Portfolio::create(['name' => 'Otra']);
        $provider = Contact::create(['portfolio_id' => $other->id, 'kind' => 'company', 'name' => 'Ajeno']);

        $this->actingAs($user)->postJson('/api/v1/issues', [
            'property_id' => $property->id, 'assigned_contact_id' => $provider->id,
            'title' => 'Humedad', 'priority' => 'medium', 'reported_at' => today()->toDateString(),
        ])->assertNotFound();
    }

    public function test_reminder_can_be_completed_and_disappears_from_calendar(): void
    {
        [$user, $portfolio, $property] = $this->context();
        $reminder = Reminder::create([
            'portfolio_id' => $portfolio->id, 'property_id' => $property->id,
            'title' => 'Revisar seguro', 'starts_at' => now()->addDay(),
        ]);

        $this->actingAs($user)->getJson('/api/v1/calendar')->assertOk()
            ->assertJsonFragment(['id' => 'reminder-'.$reminder->id]);
        $this->actingAs($user)->putJson("/api/v1/reminders/{$reminder->id}", ['completed' => true])
            ->assertOk();
        $this->actingAs($user)->getJson('/api/v1/calendar')->assertOk()
            ->assertJsonMissing(['id' => 'reminder-'.$reminder->id]);
        $this->assertNotNull($reminder->fresh()->completed_at);
    }

    public function test_documents_can_be_filtered_by_upcoming_expiration(): void
    {
        [$user, $portfolio] = $this->context();
        Document::create([
            'portfolio_id' => $portfolio->id, 'name' => 'Seguro próximo', 'category' => 'insurance',
            'storage_key' => 'one.pdf', 'original_filename' => 'one.pdf', 'mime_type' => 'application/pdf',
            'size' => 10, 'uploaded_by' => $user->id, 'expires_at' => today()->addDays(20),
        ]);
        Document::create([
            'portfolio_id' => $portfolio->id, 'name' => 'Seguro lejano', 'category' => 'insurance',
            'storage_key' => 'two.pdf', 'original_filename' => 'two.pdf', 'mime_type' => 'application/pdf',
            'size' => 10, 'uploaded_by' => $user->id, 'expires_at' => today()->addYear(),
        ]);

        $this->actingAs($user)->getJson('/api/v1/documents?status=upcoming')
            ->assertOk()->assertJsonFragment(['name' => 'Seguro próximo'])
            ->assertJsonMissing(['name' => 'Seguro lejano']);
    }
}
