<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPlanApiTest extends TestCase
{
    public function test_the_public_site_can_read_a_safe_plan_catalog(): void
    {
        $response = $this->getJson('/api/v1/public/plans');

        $response->assertOk()
            ->assertJsonPath('plans.0.code', 'free')
            ->assertJsonPath('plans.1.code', 'founder')
            ->assertJsonPath('plans.1.price_monthly', 6.99)
            ->assertJsonMissingPath('plans.1.prices');
    }
}
