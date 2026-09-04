<?php

namespace Tests\Feature;

use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $user = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'oldpass123']);
        $portfolio = Portfolio::create(['name' => 'Cartera inicial', 'currency' => 'EUR', 'country_code' => 'ES']);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        return [$user, $portfolio];
    }

    public function test_owner_can_update_profile_and_portfolio_preferences(): void
    {
        [$user, $portfolio] = $this->owner();

        $this->actingAs($user)->putJson('/api/v1/account', [
            'name' => 'Ana García', 'email' => 'ANA.NUEVA@example.com',
            'portfolio_name' => 'Patrimonio García', 'currency' => 'EUR', 'country_code' => 'pt',
        ])->assertOk()
            ->assertJsonPath('user.email', 'ana.nueva@example.com')
            ->assertJsonPath('portfolio.name', 'Patrimonio García')
            ->assertJsonPath('portfolio.country_code', 'PT');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Ana García']);
        $this->assertDatabaseHas('portfolios', ['id' => $portfolio->id, 'name' => 'Patrimonio García']);
    }

    public function test_email_must_remain_unique(): void
    {
        [$user] = $this->owner();
        User::factory()->create(['email' => 'used@example.com']);

        $this->actingAs($user)->putJson('/api/v1/account', [
            'name' => 'Ana', 'email' => 'used@example.com', 'portfolio_name' => 'Cartera',
            'currency' => 'EUR', 'country_code' => 'ES',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_password_change_requires_the_current_password_and_confirmation(): void
    {
        [$user] = $this->owner();

        $this->actingAs($user)->putJson('/api/v1/account/password', [
            'current_password' => 'incorrecta', 'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->actingAs($user)->putJson('/api/v1/account/password', [
            'current_password' => 'oldpass123', 'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertOk()->assertJsonPath('message', 'Contraseña actualizada.');

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_usage_reports_the_plan_limit_and_account_deletion_requires_explicit_confirmation(): void
    {
        Storage::fake('local');
        [$user, $portfolio] = $this->owner();
        Storage::disk('local')->put("portfolios/{$portfolio->id}/documents/file.pdf", 'private');

        $this->actingAs($user)->getJson('/api/v1/account/usage')->assertOk()
            ->assertJsonPath('code', 'starter')->assertJsonPath('storage.limit', 262144000);
        $this->actingAs($user)->deleteJson('/api/v1/account', [
            'current_password' => 'oldpass123', 'confirmation' => 'NO',
        ])->assertUnprocessable();
        $this->actingAs($user)->deleteJson('/api/v1/account', [
            'current_password' => 'oldpass123', 'confirmation' => 'ELIMINAR',
        ])->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('portfolios', ['id' => $portfolio->id]);
        Storage::disk('local')->assertMissing("portfolios/{$portfolio->id}/documents/file.pdf");
    }
}
