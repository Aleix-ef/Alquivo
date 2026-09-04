<?php

namespace Tests\Feature;

use App\Domain\Portfolio\Models\Portfolio;
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
            'terms_accepted' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('portfolio.name', 'Patrimonio Ruiz')
            ->assertJsonStructure(['user', 'portfolio'])
            ->assertJsonMissingPath('token');
        $this->assertDatabaseHas('portfolio_members', ['role' => 'owner']);
    }

    public function test_authenticated_user_can_create_a_property_in_their_portfolio(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana Ruiz', 'email' => 'ana@example.com',
            'password' => 'secure123', 'password_confirmation' => 'secure123',
            'terms_accepted' => true,
        ]);

        $this->actingAs(User::where('email', 'ana@example.com')->firstOrFail())->postJson('/api/v1/properties', [
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
