<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Finance\Models\RecurringRule;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Finance\Services\RecurringTransactionService;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Mi cartera', 'trial_ends_at' => now()->subDay()]);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        return [$user, $portfolio];
    }

    public function test_disabled_features_are_not_promised_or_accessible_but_history_can_be_deleted(): void
    {
        config(['beta.fiscality_enabled' => false, 'beta.assistant_validated' => false, 'support.email' => 'soporte@alquivo.com']);
        Http::preventStrayRequests();
        $this->getJson('/api/v1/public/config')->assertOk()->assertExactJson([
            'assistant' => false, 'fiscality' => false, 'billing_enabled' => false, 'support_email' => 'soporte@alquivo.com',
        ]);
        $catalog = $this->getJson('/api/v1/public/plans')->assertOk()->getContent();
        $this->assertStringNotContainsString('Fiscalidad', $catalog);
        $this->assertStringNotContainsString('Asistente', $catalog);
        [$user, $portfolio] = $this->owner();
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);
        $this->actingAs($user)->getJson('/api/v1/fiscality')->assertNotFound();
        $this->postJson('/api/v1/fiscality/2025/reports')->assertNotFound();
        $this->postJson('/api/v1/assistant/activation')->assertNotFound();
        $this->postJson('/api/v1/assistant/conversations')->assertNotFound();
        $this->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", ['message' => 'Hola'])->assertNotFound();
        $this->getJson('/api/v1/assistant/conversations')->assertOk()->assertJsonPath('available', false);
        $this->deleteJson('/api/v1/assistant/activation')->assertSuccessful();
        $this->assertDatabaseCount('ai_conversations', 0);
        Http::assertNothingSent();
    }

    public function test_notification_links_use_the_frontend_and_valid_api_signatures(): void
    {
        config(['services.frontend_url' => 'https://app.example.test']);
        [$user] = $this->owner();
        $user->forceFill(['email_verified_at' => null])->save();
        $reset = (new ResetPassword('test-token'))->toMail($user);
        $this->assertStringStartsWith('https://app.example.test/reset-password?', $reset->actionUrl);
        parse_str(parse_url($reset->actionUrl, PHP_URL_QUERY), $resetQuery);
        $this->assertSame(['token' => 'test-token', 'email' => $user->email], $resetQuery);
        // Exercise toMail, not Notification::fake, so missing named routes are detected.
        $verify = (new VerifyEmail)->toMail($user);
        $this->assertStringStartsWith('https://app.example.test/verify-email?', $verify->actionUrl);
        parse_str(parse_url($verify->actionUrl, PHP_URL_QUERY), $query);
        $endpoint = "/api/v1/auth/email/verify/{$query['id']}/{$query['hash']}?".http_build_query(array_intersect_key($query, array_flip(['expires', 'signature'])));
        $this->actingAs(User::factory()->create())->getJson($endpoint)->assertForbidden();
        $this->actingAs($user)->getJson($endpoint.'&changed=1')->assertForbidden();
        $this->getJson($endpoint)->assertOk()->assertJsonPath('verified', true);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->travel(61)->minutes();
        $this->getJson($endpoint)->assertForbidden();
    }

    public function test_downgrade_preserves_read_export_and_first_property_but_rejects_writes_to_extras(): void
    {
        [$user, $portfolio] = $this->owner();
        $first = $portfolio->properties()->create(['name' => 'Primero', 'type' => 'housing', 'address_line' => 'Calle 1']);
        $extra = $portfolio->properties()->create(['name' => 'Segundo', 'type' => 'housing', 'address_line' => 'Calle 2']);
        $this->actingAs($user)->getJson('/api/v1/plans')->assertOk()->assertJsonPath('current.properties.read_only_count', 1)->assertJsonPath('current.properties.editable_ids', [$first->id]);
        $this->getJson("/api/v1/properties/{$extra->id}")->assertOk();
        $this->get('/api/v1/exports/properties')->assertOk();
        $this->putJson("/api/v1/properties/{$extra->id}", ['name' => 'Cambio'])->assertUnprocessable()->assertJsonValidationErrors('plan');
        $this->putJson("/api/v1/properties/{$first->id}", ['name' => 'Editable'])->assertOk();
        $payload = ['property_id' => $extra->id, 'direction' => 'expense', 'category' => 'other', 'description' => 'Gasto', 'amount' => 10, 'transaction_date' => today()->toDateString(), 'status' => 'paid'];
        $this->postJson('/api/v1/transactions', $payload)->assertUnprocessable()->assertJsonValidationErrors('plan');
        $transaction = Transaction::create([...$payload, 'portfolio_id' => $portfolio->id]);
        $this->putJson("/api/v1/transactions/{$transaction->id}", ['property_id' => $first->id])->assertUnprocessable()->assertJsonValidationErrors('plan');
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'property_id' => $extra->id]);
        $this->assertDatabaseCount('properties', 2);
        $portfolio->update(['trial_ends_at' => now()->addDay()]);
        $this->putJson("/api/v1/properties/{$extra->id}", ['name' => 'Prueba activa'])->assertOk();
    }

    public function test_extra_property_recurring_generation_pauses_and_resumes_without_duplicates(): void
    {
        [, $portfolio] = $this->owner();
        $portfolio->properties()->create(['name' => 'Primero', 'type' => 'housing', 'address_line' => 'Calle 1']);
        $extra = $portfolio->properties()->create(['name' => 'Segundo', 'type' => 'housing', 'address_line' => 'Calle 2']);
        $rule = RecurringRule::create(['portfolio_id' => $portfolio->id, 'property_id' => $extra->id, 'direction' => 'expense', 'category' => 'other', 'description' => 'Comunidad', 'amount' => 10, 'frequency' => 'monthly', 'starts_on' => today(), 'next_date' => today(), 'active' => true]);
        $service = app(RecurringTransactionService::class);
        $this->assertSame(0, $service->generateDue($rule));
        $this->assertDatabaseCount('transactions', 0);
        $portfolio->update(['plan' => 'founder']);
        $this->assertSame(1, $service->generateDue($rule));
        $this->assertSame(0, $service->generateDue($rule));
    }

    public function test_existing_amounts_cannot_be_relabelled_in_another_currency(): void
    {
        [$user, $portfolio] = $this->owner();
        $this->actingAs($user)->putJson('/api/v1/account', ['name' => $user->name, 'email' => $user->email, 'portfolio_name' => $portfolio->name, 'currency' => 'USD', 'country_code' => 'ES'])->assertUnprocessable()->assertJsonValidationErrors('currency');
        $this->assertSame('EUR', $portfolio->fresh()->currency);
    }
}
