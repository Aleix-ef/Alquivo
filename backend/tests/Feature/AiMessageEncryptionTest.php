<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiMessage;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiMessageEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_content_and_metadata_are_encrypted_at_rest_without_changing_serialization(): void
    {
        $conversation = $this->conversation();
        $metadata = ['kind' => 'proposal', 'preview' => ['description' => 'Fontanería sintética privada', 'amount' => '84.00']];
        $message = $conversation->messages()->create(['role' => 'assistant', 'content' => 'Descripción privada de prueba', 'metadata' => $metadata]);
        $raw = DB::table('ai_messages')->where('id', $message->id)->first();
        $this->assertSame('', $raw->content);
        $this->assertNull($raw->metadata);
        $this->assertStringNotContainsString('privada', $raw->content_encrypted);
        $this->assertStringNotContainsString('Fontanería', $raw->metadata_encrypted);
        $this->assertSame('Descripción privada de prueba', Crypt::decryptString($raw->content_encrypted));
        $this->assertSame($metadata, json_decode(Crypt::decryptString($raw->metadata_encrypted), true));
        $serialized = $message->fresh()->toArray();
        $this->assertSame('Descripción privada de prueba', $serialized['content']);
        $this->assertSame($metadata, $serialized['metadata']);
        $this->assertArrayNotHasKey('content_encrypted', $serialized);
        $this->assertArrayNotHasKey('metadata_encrypted', $serialized);
    }

    public function test_updates_preserve_encryption_and_support_null_metadata_and_empty_content(): void
    {
        $message = $this->conversation()->messages()->create(['role' => 'assistant', 'content' => 'Inicial', 'metadata' => ['kind' => 'answer']]);
        $message->update(['content' => '', 'metadata' => null]);
        $this->assertSame('', $message->fresh()->content);
        $this->assertNull($message->fresh()->metadata);
        $raw = DB::table('ai_messages')->where('id', $message->id)->first();
        $this->assertNotNull($raw->content_encrypted);
        $this->assertNull($raw->metadata_encrypted);
        $this->assertSame('', $raw->content);
    }

    public function test_legacy_rows_are_readable_and_editing_reencrypts_both_fields(): void
    {
        $id = $this->legacyMessage($this->conversation()->id);
        $message = AiMessage::findOrFail($id);
        $this->assertSame('Mensaje antiguo', $message->content);
        $this->assertSame(['kind' => 'answer', 'private' => 'Metadata antigua'], $message->metadata);
        $message->update(['content' => $message->content, 'metadata' => $message->metadata]);
        $raw = DB::table('ai_messages')->where('id', $id)->first();
        $this->assertSame('', $raw->content);
        $this->assertNull($raw->metadata);
        $this->assertNotNull($raw->content_encrypted);
        $this->assertNotNull($raw->metadata_encrypted);
    }

    public function test_corrupt_ciphertext_never_falls_back_to_plaintext_content(): void
    {
        $id = $this->legacyMessage($this->conversation()->id);
        DB::table('ai_messages')->where('id', $id)->update(['content_encrypted' => 'not-a-valid-ciphertext']);
        $this->expectException(DecryptException::class);
        AiMessage::findOrFail($id)->content;
    }

    public function test_corrupt_metadata_never_falls_back_to_legacy_metadata(): void
    {
        $id = $this->legacyMessage($this->conversation()->id);
        DB::table('ai_messages')->where('id', $id)->update(['metadata_encrypted' => 'not-a-valid-ciphertext']);
        $this->expectException(DecryptException::class);
        AiMessage::findOrFail($id)->metadata;
    }

    public function test_allowed_large_unicode_content_survives_roundtrip(): void
    {
        $content = str_repeat('🏠', 12000);
        $message = $this->conversation()->messages()->create(['role' => 'assistant', 'content' => $content]);
        $this->assertSame($content, $message->fresh()->content);
        $this->assertGreaterThan(65535, strlen(DB::table('ai_messages')->where('id', $message->id)->value('content_encrypted')));
    }

    public function test_migration_backfills_multiple_chunks_and_rollback_preserves_plaintext(): void
    {
        $migration = require database_path('migrations/2026_09_20_000150_encrypt_ai_message_content.php');
        $migration->down();
        $conversationId = $this->conversation()->id;
        $ids = [];
        for ($index = 0; $index < 205; $index++) {
            $ids[] = $this->legacyMessage($conversationId);
        }
        $nullId = DB::table('ai_messages')->insertGetId(['conversation_id' => $conversationId, 'role' => 'user', 'content' => 'Sin metadata', 'metadata' => null]);
        $migration->up();
        $this->assertSame(206, DB::table('ai_messages')->where('content', '')->whereNull('metadata')->whereNotNull('content_encrypted')->count());
        $this->assertSame('Mensaje antiguo', AiMessage::findOrFail(end($ids))->content);
        $this->assertSame('Metadata antigua', AiMessage::findOrFail($ids[0])->metadata['private']);
        $this->assertNull(AiMessage::findOrFail($nullId)->metadata);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('ai_messages', 'content_encrypted'));
        $this->assertSame('Mensaje antiguo', DB::table('ai_messages')->where('id', $ids[0])->value('content'));
        $this->assertSame('Metadata antigua', json_decode(DB::table('ai_messages')->where('id', $ids[0])->value('metadata'), true)['private']);
        // Restore the migrated shape inside this test's transaction for subsequent tests.
        $migration->up();
    }

    public function test_rollback_fails_without_discarding_corrupt_encrypted_data(): void
    {
        $message = $this->conversation()->messages()->create(['role' => 'user', 'content' => 'Contenido que no debe perderse']);
        DB::table('ai_messages')->where('id', $message->id)->update(['content_encrypted' => 'corrupt']);
        $migration = require database_path('migrations/2026_09_20_000150_encrypt_ai_message_content.php');
        try {
            $migration->down();
            $this->fail('Corruption must prevent removing the encrypted columns.');
        } catch (DecryptException) {
            $this->assertTrue(Schema::hasColumn('ai_messages', 'content_encrypted'));
            $this->assertSame('corrupt', DB::table('ai_messages')->where('id', $message->id)->value('content_encrypted'));
        }
    }

    public function test_backfill_can_resume_without_overwriting_already_encrypted_messages(): void
    {
        $conversation = $this->conversation();
        $encrypted = $conversation->messages()->create(['role' => 'assistant', 'content' => 'Contenido nuevo', 'metadata' => ['private' => 'Ya cifrada']]);
        $before = DB::table('ai_messages')->where('id', $encrypted->id)->first();
        $legacyId = $this->legacyMessage($conversation->id);
        $migration = require database_path('migrations/2026_09_20_000150_encrypt_ai_message_content.php');
        $migration->up();
        $migration->up();
        $after = DB::table('ai_messages')->where('id', $encrypted->id)->first();
        $this->assertSame($before->content_encrypted, $after->content_encrypted);
        $this->assertSame($before->metadata_encrypted, $after->metadata_encrypted);
        $this->assertSame('Contenido nuevo', $encrypted->fresh()->content);
        $this->assertSame('Mensaje antiguo', AiMessage::findOrFail($legacyId)->content);
        $this->assertSame('Metadata antigua', AiMessage::findOrFail($legacyId)->metadata['private']);
    }

    public function test_api_returns_decrypted_messages_only_to_the_conversation_owner(): void
    {
        $conversation = $this->conversation();
        $owner = User::findOrFail($conversation->user_id);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Texto para el propietario', 'metadata' => ['kind' => 'answer']]);
        $this->actingAs($owner)->getJson('/api/v1/assistant/conversations/'.$conversation->id)->assertOk()
            ->assertJsonPath('messages.0.content', 'Texto para el propietario')
            ->assertJsonPath('messages.0.metadata.kind', 'answer')
            ->assertJsonMissingPath('messages.0.content_encrypted')
            ->assertJsonMissingPath('messages.0.metadata_encrypted');
        $this->actingAs(User::factory()->create())->getJson('/api/v1/assistant/conversations/'.$conversation->id)->assertNotFound();
    }

    private function conversation(): AiConversation
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera sintética', 'currency' => 'EUR', 'country_code' => 'ES']);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        return AiConversation::create(['user_id' => $user->id, 'portfolio_id' => $portfolio->id]);
    }

    private function legacyMessage(int $conversationId): int
    {
        return DB::table('ai_messages')->insertGetId([
            'conversation_id' => $conversationId, 'role' => 'user', 'content' => 'Mensaje antiguo',
            'metadata' => json_encode(['kind' => 'answer', 'private' => 'Metadata antigua']),
        ]);
    }
}
