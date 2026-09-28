<?php

namespace Tests\Feature;

use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PlanService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndPropertyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_user_and_portfolio(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana Ruiz',
            'email' => 'ana@example.com',
            'password' => 'secure123',
            'password_confirmation' => 'secure123',
            'portfolio_name' => 'Patrimonio Ruiz',
            'terms_accepted' => true, 'terms_version' => config('legal.terms_version'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('portfolio.name', 'Patrimonio Ruiz')
            ->assertJsonStructure(['user', 'portfolio'])
            ->assertJsonMissingPath('token');
        $this->assertDatabaseHas('portfolio_members', ['role' => 'owner']);
        $registered = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame(config('legal.terms_version'), $registered->terms_version);
        $this->assertNotNull($registered->terms_accepted_at);
        $this->assertNull($registered->email_verified_at);
        $this->assertNull($registered->portfolio()->trial_ends_at);
        $this->assertSame('free', app(PlanService::class)->effectiveCode($registered->portfolio()));
    }

    public function test_registration_rejects_missing_or_outdated_legal_acceptance(): void
    {
        $payload = [
            'name' => 'Ana Ruiz', 'email' => 'ana@example.com',
            'password' => 'secure123', 'password_confirmation' => 'secure123',
            'terms_accepted' => true, 'terms_version' => config('legal.terms_version'),
        ];
        $this->postJson('/api/v1/auth/register', [...$payload, 'terms_version' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('terms_version');
        $this->postJson('/api/v1/auth/register', [...$payload, 'terms_version' => '2026-07'])
            ->assertUnprocessable()->assertJsonValidationErrors('terms_version');
        $this->postJson('/api/v1/auth/register', [...$payload, 'terms_accepted' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('terms_accepted');
        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
        $this->assertDatabaseCount('portfolios', 0);
    }

    public function test_authenticated_user_can_create_a_property_in_their_portfolio(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana Ruiz', 'email' => 'ana@example.com',
            'password' => 'secure123', 'password_confirmation' => 'secure123',
            'terms_accepted' => true, 'terms_version' => config('legal.terms_version'),
        ]);

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->actingAs($user)->postJson('/api/v1/properties', [
            'name' => 'Piso Gran Vía',
            'type' => 'housing',
            'address_line' => 'Gran Vía 10',
            'current_value' => 240000,
        ])->assertCreated()->assertJsonPath('name', 'Piso Gran Vía');

        $this->assertDatabaseHas('properties', ['name' => 'Piso Gran Vía']);
        $this->assertDatabaseHas('property_valuations', ['amount' => 240000, 'source' => 'owner']);
    }

    public function test_updating_current_value_keeps_valuation_history(): void
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Piso', 'type' => 'housing', 'address_line' => 'Calle 1', 'current_value' => 100000]);

        $this->actingAs($user)->putJson("/api/v1/properties/{$property->id}", ['current_value' => 112000])
            ->assertOk()->assertJsonPath('current_value', '112000.00');

        $this->assertDatabaseHas('property_valuations', ['property_id' => $property->id, 'amount' => 112000]);
    }
}
