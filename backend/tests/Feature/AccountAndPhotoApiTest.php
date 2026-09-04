<?php

namespace Tests\Feature;

use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountAndPhotoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_request_does_not_reveal_if_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_can_be_reset_and_existing_tokens_are_revoked(): void
    {
        $user = User::factory()->create();
        $user->createToken('old');
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertOk();

        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_owner_can_upload_a_private_property_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Piso', 'type' => 'housing', 'address_line' => 'Calle 1']);

        $response = $this->actingAs($user)->postJson("/api/v1/properties/{$property->id}/photos", [
            'photo' => UploadedFile::fake()->createWithContent(
                'salon.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
            ),
        ])->assertCreated()->assertJsonPath('is_cover', true)->assertJsonMissingPath('storage_key');

        $photo = $property->photos()->findOrFail($response->json('id'));
        Storage::disk('local')->assertExists($photo->storage_key);
    }
}
