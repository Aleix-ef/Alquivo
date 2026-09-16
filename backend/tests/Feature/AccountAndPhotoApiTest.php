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

        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('properties.0.photos.0.id', $photo->id)
            ->assertJsonMissingPath('properties.0.photos.0.storage_key');
        $this->get("/api/v1/property-photos/{$photo->id}")->assertOk();

        $otherUser = User::factory()->create();
        $otherPortfolio = Portfolio::create(['name' => 'Otra cartera']);
        $otherPortfolio->members()->attach($otherUser, ['role' => 'owner']);
        $this->actingAs($otherUser)->getJson('/api/v1/dashboard')->assertOk()->assertJsonCount(0, 'properties');
        $this->getJson("/api/v1/property-photos/{$photo->id}")->assertNotFound();
    }

    public function test_owner_can_choose_a_cover_and_delete_a_photo_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Piso', 'type' => 'housing', 'address_line' => 'Calle 1']);
        $image = fn (string $name) => UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        $this->actingAs($user);
        $firstId = $this->postJson("/api/v1/properties/{$property->id}/photos", ['photo' => $image('one.png')])->assertCreated()->json('id');
        $secondId = $this->postJson("/api/v1/properties/{$property->id}/photos", ['photo' => $image('two.png')])->assertCreated()->json('id');
        $second = $property->photos()->findOrFail($secondId);

        $this->putJson("/api/v1/property-photos/{$secondId}/cover")->assertOk()->assertJsonPath('is_cover', true);
        $this->assertDatabaseHas('property_photos', ['id' => $firstId, 'is_cover' => false]);
        $this->deleteJson("/api/v1/property-photos/{$secondId}")->assertNoContent();
        Storage::disk('local')->assertMissing($second->storage_key);
        $this->assertDatabaseHas('property_photos', ['id' => $firstId, 'is_cover' => true]);
    }

    public function test_empty_property_can_be_archived_but_history_is_preserved(): void
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $empty = $portfolio->properties()->create(['name' => 'Error', 'type' => 'housing', 'address_line' => 'Calle 1']);
        $withHistory = $portfolio->properties()->create(['name' => 'Con datos', 'type' => 'housing', 'address_line' => 'Calle 2']);
        $withHistory->transactions()->create([
            'portfolio_id' => $portfolio->id, 'direction' => 'expense', 'category' => 'other',
            'description' => 'Gasto', 'amount' => 10, 'transaction_date' => today(), 'status' => 'paid',
        ]);

        $this->actingAs($user)->deleteJson("/api/v1/properties/{$withHistory->id}")->assertUnprocessable();
        $this->deleteJson("/api/v1/properties/{$empty->id}")->assertNoContent();
        $this->assertSoftDeleted('properties', ['id' => $empty->id]);
        $this->assertDatabaseHas('properties', ['id' => $withHistory->id, 'deleted_at' => null]);
    }
}
