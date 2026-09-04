<?php

namespace Tests\Feature;

use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_a_property_from_another_portfolio(): void
    {
        $user = User::factory()->create();
        $own = Portfolio::create(['name' => 'Cartera A']);
        $other = Portfolio::create(['name' => 'Cartera B']);
        $own->members()->attach($user, ['role' => 'owner']);
        $property = $other->properties()->create(['name' => 'Ajena', 'type' => 'housing', 'address_line' => 'Otra calle']);

        $this->actingAs($user)->getJson("/api/v1/properties/{$property->id}")->assertNotFound();
    }
}
