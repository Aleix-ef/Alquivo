<?php

namespace Tests\Feature;

use App\Domain\Attention\Models\Issue;
use App\Domain\Attention\Models\Reminder;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BetaSecurityCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Notification::fake();
        Storage::fake('local');
    }

    private function context(string $resource): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Sintética', 'plan' => 'founder']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Piso sintético', 'type' => 'housing', 'address_line' => 'Calle de prueba']);
        $contact = Contact::create(['portfolio_id' => $portfolio->id, 'kind' => 'person', 'name' => 'Contacto sintético']);
        $lease = Lease::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'draft',
            'start_date' => today(), 'monthly_rent' => 550, 'payment_day' => 5]);
        $lease->participants()->attach($contact, ['role' => 'tenant', 'is_primary' => true]);
        $payloads = [
            'properties' => ['name' => 'Nueva', 'type' => 'housing', 'address_line' => 'Calle sintética'],
            'contacts' => ['name' => 'Nuevo contacto', 'kind' => 'person'],
            'leases' => ['property_id' => $property->id, 'contact_ids' => [$contact->id], 'status' => 'draft',
                'start_date' => today()->toDateString(), 'monthly_rent' => 550, 'payment_day' => 5],
            'transactions' => ['direction' => 'income', 'category' => 'other', 'description' => 'Prueba',
                'amount' => 10, 'status' => 'paid', 'transaction_date' => today()->toDateString()],
            'issues' => ['property_id' => $property->id, 'title' => 'Prueba', 'priority' => 'low', 'reported_at' => today()->toDateString()],
            'reminders' => ['title' => 'Prueba', 'starts_at' => now()->addDay()->toDateTimeString()],
        ];
        $record = match ($resource) {
            'properties' => $property,
            'contacts' => $contact,
            'leases' => $lease,
            'transactions' => Transaction::create(['portfolio_id' => $portfolio->id, ...$payloads[$resource]]),
            'issues' => Issue::create(['portfolio_id' => $portfolio->id, 'status' => 'open', ...$payloads[$resource]]),
            'reminders' => Reminder::create(['portfolio_id' => $portfolio->id, ...$payloads[$resource]]),
        };

        return [$user, $record, $payloads[$resource]];
    }

    public static function textFields(): array
    {
        return [
            'contacts' => ['contacts', 'notes'], 'properties' => ['properties', 'notes'],
            'leases' => ['leases', 'notes'], 'income' => ['transactions', 'notes', 'income'],
            'expense' => ['transactions', 'notes', 'expense'],
            'issues' => ['issues', 'description'], 'reminders' => ['reminders', 'description'],
        ];
    }

    #[DataProvider('textFields')]
    public function test_oversized_text_is_rejected_on_create_and_update_without_mutation(string $resource, string $field, ?string $direction = null): void
    {
        [$user, $record, $payload] = $this->context($resource);
        if ($direction) {
            $payload['direction'] = $direction;
        }
        $before = $record->getTable();
        $count = $record->newQuery()->count();
        $text = str_repeat('á', 10001);
        $this->actingAs($user)->postJson('/api/v1/'.$resource, [...$payload, $field => $text])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->putJson('/api/v1/'.$resource.'/'.$record->id, [$field => $text])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount($before, $count);
        $this->assertNull($record->fresh()->getAttribute($field));
        Http::assertNothingSent();
    }

    #[DataProvider('textFields')]
    public function test_text_at_the_limit_and_nullable_edits_still_work(string $resource, string $field, ?string $direction = null): void
    {
        [$user, $record, $payload] = $this->context($resource);
        if ($direction) {
            $payload['direction'] = $direction;
        }
        $text = str_repeat('á', 10000);
        $this->actingAs($user)->postJson('/api/v1/'.$resource, [...$payload, $field => $text])->assertCreated()->assertJsonPath($field, $text);
        $this->putJson('/api/v1/'.$resource.'/'.$record->id, [$field => $text])->assertOk()->assertJsonPath($field, $text);
        $this->putJson('/api/v1/'.$resource.'/'.$record->id, [$field => null])->assertOk()->assertJsonPath($field, null);
    }

    public function test_renewal_enforces_the_same_note_limit(): void
    {
        [$user, $lease] = $this->context('leases');
        $payload = ['start_date' => today()->addYear()->toDateString(), 'monthly_rent' => 600, 'payment_day' => 5];
        $this->actingAs($user)->postJson('/api/v1/leases/'.$lease->id.'/renew', [...$payload, 'notes' => str_repeat('x', 10001)])
            ->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->assertDatabaseCount('leases', 1);
        $this->postJson('/api/v1/leases/'.$lease->id.'/renew', [...$payload, 'notes' => 'Renovación sintética'])->assertCreated();
    }

    public static function portfolioEndpoints(): array
    {
        $get = ['/account/usage', '/plans', '/dashboard', '/properties', '/contacts', '/leases', '/transactions',
            '/recurring-rules', '/documents', '/issues', '/calendar', '/reports/overview', '/exports/properties',
            '/fiscality', '/document-ai', '/assistant/conversations',
            '/assistant/proposals/11111111-1111-4111-8111-111111111111', '/assistant/runs/11111111-1111-4111-8111-111111111111'];
        $post = ['/properties', '/contacts', '/leases', '/transactions', '/recurring-rules', '/documents', '/issues',
            '/reminders', '/assistant/activation', '/assistant/conversations', '/billing/checkout', '/billing/portal', '/document-ai/consent'];

        return [...array_map(fn ($p) => ['GET', $p], $get), ...array_map(fn ($p) => ['POST', $p], $post), ['PUT', '/account']];
    }

    #[DataProvider('portfolioEndpoints')]
    public function test_missing_portfolio_is_a_controlled_404(string $method, string $path): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->json($method, '/api/v1'.$path)->assertNotFound();
        $this->assertDatabaseCount('portfolios', 0);
        $this->assertDatabaseCount('properties', 0);
        $this->assertDatabaseCount('ai_action_proposals', 0);
        Http::assertNothingSent();
    }

    public function test_account_without_portfolio_can_still_use_security_support_and_delete_itself(): void
    {
        $user = User::factory()->create(['password' => 'testpass123']);
        $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('portfolio', null);
        $this->getJson('/api/v1/account/two-factor')->assertOk();
        $this->getJson('/api/v1/support')->assertOk();
        $this->putJson('/api/v1/account/password', ['current_password' => 'testpass123', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertOk();
        $this->deleteJson('/api/v1/account', ['current_password' => 'newpass123', 'confirmation' => 'ELIMINAR'])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
