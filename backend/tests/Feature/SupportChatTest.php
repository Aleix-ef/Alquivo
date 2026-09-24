<?php

namespace Tests\Feature;

use App\Domain\Documents\Services\PrivateFileDeletion;
use App\Domain\Documents\Services\PrivateFileVault;
use App\Domain\Documents\Services\UploadScanner;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Support\Models\SupportAttachment;
use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost'], 'mail.default' => 'log']);
        $this->withHeader('Origin', 'http://localhost');
        Storage::fake('local');
    }

    private function payload(array $changes = []): array
    {
        return [...['conversation_id' => (string) Str::uuid(), 'client_id' => (string) Str::uuid(), 'name' => 'Visitante', 'email' => 'visitor@example.test', 'subject' => 'No puedo subir una factura', 'message' => 'Me aparece un error al adjuntar el documento.', 'privacy_acknowledged' => true], ...$changes];
    }

    private function thread(User $user): string
    {
        return $this->actingAs($user)->postJson('/api/v1/support/chat/conversations', $this->payload())
            ->assertCreated()->json('id');
    }

    private function agent(): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        DB::table('support_agents')->insert(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function file(string $name = 'capture.png', ?string $bytes = null): UploadedFile
    {
        $fake = UploadedFile::fake()->createWithContent($name, $bytes ?? base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jq1sAAAAASUVORK5CYII='));
        $this->files[] = $fake;

        return new UploadedFile($fake->getRealPath(), $name, null, null, true);
    }

    public function test_customer_and_authorized_agent_reply_in_the_same_persistent_thread(): void
    {
        $customer = User::factory()->create();
        $id = $this->thread($customer);
        $this->getJson('/api/v1/support/chat/conversations')->assertOk()->assertJsonCount(1, 'conversations')->assertJsonPath('can_manage', false);
        $agent = $this->agent();
        $this->actingAs($agent)->getJson('/api/v1/support/team/conversations')->assertOk()->assertJsonPath('conversations.0.unread', true)->assertJsonPath('conversations.0.name', $customer->name);
        $this->postJson("/api/v1/support/team/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'message' => 'Prueba a subir el archivo en formato PDF.'])->assertCreated()->assertJsonPath('status', 'waiting_customer');
        $this->actingAs($customer)->getJson('/api/v1/support/chat/conversations')->assertJsonPath('conversations.0.unread', true);
        $history = $this->getJson("/api/v1/support/chat/conversations/{$id}")->assertOk()->assertJsonCount(2, 'messages')->assertJsonPath('messages.1.author_role', 'support');
        $this->postJson("/api/v1/support/chat/conversations/{$id}/read", ['message_id' => $history->json('messages.1.id')])->assertNoContent();
        $this->getJson('/api/v1/support/chat/conversations')->assertJsonPath('conversations.0.unread', false);
        $this->postJson("/api/v1/support/chat/conversations/{$id}/close")->assertOk()->assertJsonPath('status', 'closed');
        $this->postJson("/api/v1/support/chat/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'message' => 'Necesito revisar otro detalle.'])->assertCreated()->assertJsonPath('status', 'waiting_support');
    }

    public function test_accounts_cannot_read_reply_mark_or_close_other_accounts_conversations(): void
    {
        $id = $this->thread(User::factory()->create());
        $messageId = SupportMessage::first()->id;
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/support/chat/conversations')->assertJsonCount(0, 'conversations');
        $this->getJson("/api/v1/support/chat/conversations/{$id}")->assertNotFound();
        $this->postJson("/api/v1/support/chat/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'message' => 'Intento ajeno'])->assertNotFound();
        $this->postJson("/api/v1/support/chat/conversations/{$id}/read", ['message_id' => $messageId])->assertNotFound();
        $this->postJson("/api/v1/support/chat/conversations/{$id}/close")->assertNotFound();
        $created = $this->postJson('/api/v1/support/chat/conversations', $this->payload(['conversation_id' => $id]))->assertCreated();
        $this->assertNotSame($id, $created->json('id'));
    }

    public function test_staff_permission_cannot_be_self_assigned_and_requires_verification_and_two_factor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/support/team/conversations')->assertForbidden();
        $this->postJson('/api/v1/support/chat/conversations', $this->payload(['is_support_agent' => true, 'author_role' => 'support', 'user_id' => 999]))->assertCreated();
        $this->assertSame('customer', SupportMessage::first()->author_role);
        $this->assertSame($user->id, SupportConversation::first()->user_id);
        $this->assertDatabaseCount('support_agents', 0);
        DB::table('support_agents')->insert(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/api/v1/support/team/conversations')->assertForbidden();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'email_verified_at' => null])->save();
        $this->actingAs($user->fresh())->getJson('/api/v1/support/team/conversations')->assertForbidden();
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user->fresh())->getJson('/api/v1/support/team/conversations')->assertOk();
        DB::table('support_agents')->where('user_id', $user->id)->delete();
        $this->getJson('/api/v1/support/team/conversations')->assertForbidden();
    }

    public function test_guest_conversations_are_bound_to_a_session_not_to_an_email_or_a_guessed_id(): void
    {
        $first = str_repeat('a', 64);
        $id = $this->withSession(['support_guest_key' => $first])->postJson('/api/v1/public/support/chat/conversations', $this->payload())->assertCreated()->json('id');
        $this->getJson("/api/v1/public/support/chat/conversations/{$id}")->assertOk()->assertJsonCount(1, 'messages');
        $this->withSession(['support_guest_key' => str_repeat('b', 64)])->getJson('/api/v1/public/support/chat/conversations')->assertJsonCount(0, 'conversations');
        $this->getJson("/api/v1/public/support/chat/conversations/{$id}?email=visitor@example.test")->assertNotFound();
        $this->postJson("/api/v1/public/support/chat/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'message' => 'Otro visitante'])->assertNotFound();
        $this->withSession(['support_guest_key' => $first])->getJson("/api/v1/public/support/chat/conversations/{$id}")->assertOk()->assertJsonMissingPath('conversation.guest_key_hash');
    }

    public function test_guest_without_a_first_party_session_cannot_create_a_chat(): void
    {
        $this->withHeader('Origin', 'https://untrusted.invalid')->postJson('/api/v1/public/support/chat/conversations', $this->payload())->assertStatus(419);
        $this->assertDatabaseCount('support_conversations', 0);
    }

    public function test_public_guest_cannot_access_an_authenticated_thread_even_in_the_same_browser(): void
    {
        $id = $this->thread(User::factory()->create());
        $this->getJson("/api/v1/public/support/chat/conversations/{$id}")->assertNotFound();
    }

    public function test_retried_creation_and_message_are_idempotent_and_cannot_replace_text(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->payload();
        $id = $this->postJson('/api/v1/support/chat/conversations', $data)->assertCreated()->json('id');
        $this->postJson('/api/v1/support/chat/conversations', $data)->assertCreated();
        $this->assertDatabaseCount('support_conversations', 1);
        $this->assertDatabaseCount('support_messages', 1);
        $reply = ['client_id' => (string) Str::uuid(), 'message' => 'Un detalle adicional'];
        $this->postJson("/api/v1/support/chat/conversations/{$id}/messages", $reply)->assertCreated();
        $this->postJson("/api/v1/support/chat/conversations/{$id}/messages", $reply)->assertCreated();
        $this->postJson("/api/v1/support/chat/conversations/{$id}/messages", [...$reply, 'message' => 'Texto modificado'])->assertStatus(409);
        $this->assertDatabaseCount('support_messages', 2);
    }

    public function test_message_and_contact_details_are_encrypted_at_rest_and_no_sender_identity_is_exposed_to_customers(): void
    {
        $user = User::factory()->create();
        $id = $this->thread($user);
        $row = DB::table('support_conversations')->where('id', $id)->first();
        $this->assertStringNotContainsString($user->email, $row->email);
        $this->assertStringNotContainsString('No puedo subir', $row->subject);
        $body = DB::table('support_messages')->value('body');
        $this->assertStringNotContainsString('Me aparece un error', $body);
        $this->getJson("/api/v1/support/chat/conversations/{$id}")->assertJsonMissingPath('conversation.email')->assertJsonMissingPath('messages.0.author_user_id');
    }

    public function test_uploads_are_scanned_encrypted_and_only_downloadable_by_the_owner_or_team(): void
    {
        $user = User::factory()->create();
        $this->mock(UploadScanner::class)->shouldReceive('scan')->once();
        $data = $this->payload(['attachments' => [$this->file()]]);
        $id = $this->actingAs($user)->postJson('/api/v1/support/chat/conversations', $data)->assertCreated()->json('id');
        $file = SupportAttachment::first();
        $this->assertStringStartsWith(PrivateFileVault::HEADER, Storage::disk('local')->get($file->storage_key));
        $this->getJson("/api/v1/support/chat/conversations/{$id}")->assertJsonMissingPath('messages.0.attachments.0.storage_key');
        $this->get("/api/v1/support/chat/conversations/{$id}/attachments/{$file->id}")->assertOk()->assertDownload('adjunto-1.png');
        $this->actingAs(User::factory()->create())->get("/api/v1/support/chat/conversations/{$id}/attachments/{$file->id}")->assertNotFound();
        $this->actingAs($this->agent())->get("/api/v1/support/team/conversations/{$id}/attachments/{$file->id}")->assertOk();
        $this->artisan('vault:files')->assertSuccessful();
    }

    public function test_malicious_uploads_and_scanner_failure_do_not_create_partial_threads(): void
    {
        $this->actingAs(User::factory()->create());
        $this->mock(UploadScanner::class)->shouldReceive('scan')->once()->andThrow(ValidationException::withMessages(['file' => ['Unsafe']]));
        $this->postJson('/api/v1/support/chat/conversations', $this->payload(['attachments' => [$this->file('fake.png', '<script>bad</script>')]]))->assertUnprocessable();
        $this->postJson('/api/v1/support/chat/conversations', $this->payload(['attachments' => [$this->file()]]))->assertUnprocessable();
        $this->assertDatabaseCount('support_conversations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_private_read_markers_are_monotonic_and_do_not_extend_retention(): void
    {
        $id = $this->thread(User::factory()->create());
        $old = now()->subDays(20)->startOfSecond();
        DB::table('support_conversations')->where('id', $id)->update(['updated_at' => $old]);
        $thread = SupportConversation::find($id);
        $first = $thread->messages()->first()->id;
        $second = $thread->messages()->create(['client_id' => (string) Str::uuid(), 'body' => 'Respuesta', 'author_role' => 'support'])->id;
        $this->postJson("/api/v1/support/chat/conversations/{$id}/read", ['message_id' => $second])->assertNoContent();
        $this->postJson("/api/v1/support/chat/conversations/{$id}/read", ['message_id' => $first])->assertNoContent();
        $this->assertSame($second, $thread->fresh()->customer_read_id);
        $this->assertTrue($thread->fresh()->updated_at->equalTo($old));
    }

    public function test_history_is_bounded_and_can_be_loaded_incrementally(): void
    {
        $id = $this->thread(User::factory()->create());
        $thread = SupportConversation::find($id);
        for ($i = 0; $i < 55; $i++) {
            $thread->messages()->create(['client_id' => (string) Str::uuid(), 'body' => "Respuesta {$i}", 'author_role' => 'support']);
        }
        $history = $this->getJson("/api/v1/support/chat/conversations/{$id}")->assertJsonCount(50, 'messages')->assertJsonPath('has_older', true);
        $this->getJson("/api/v1/support/chat/conversations/{$id}?before=".$history->json('messages.0.id'))->assertJsonCount(6, 'messages');
        $this->getJson("/api/v1/support/chat/conversations/{$id}?after=".$history->json('messages.49.id'))->assertJsonCount(0, 'messages');
    }

    public function test_inactive_conversations_and_files_are_pruned_but_recent_threads_remain(): void
    {
        $id = $this->thread(User::factory()->create());
        $path = "support/conversations/{$id}/".Str::uuid().'.enc';
        Storage::disk('local')->put($path, 'encrypted-test');
        DB::table('support_conversations')->where('id', $id)->update(['updated_at' => now()->subDays(181)]);
        $recent = $this->thread(User::factory()->create());
        $this->artisan('support:prune')->assertSuccessful();
        $this->assertDatabaseMissing('support_conversations', ['id' => $id]);
        $this->assertDatabaseHas('support_conversations', ['id' => $recent]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_an_account_removes_its_support_conversations_and_files(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $portfolio = Portfolio::create(['name' => 'Test']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $id = $this->thread($user);
        $path = "support/conversations/{$id}/".Str::uuid().'.enc';
        Storage::disk('local')->put($path, 'test');
        $this->deleteJson('/api/v1/account', ['current_password' => 'password123', 'confirmation' => 'ELIMINAR'])->assertNoContent();
        $this->assertDatabaseMissing('support_conversations', ['id' => $id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_staff_access_can_only_be_granted_explicitly_to_verified_two_factor_accounts(): void
    {
        $this->artisan('support:agent absent@example.test')->assertFailed();
        $user = User::factory()->create();
        $this->artisan('support:agent '.$user->email)->assertFailed();
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->artisan('support:agent '.$user->email)->assertSuccessful();
        $this->assertDatabaseHas('support_agents', ['user_id' => $user->id]);
        $this->artisan('support:agent '.$user->email.' --revoke')->assertSuccessful();
        $this->assertDatabaseMissing('support_agents', ['user_id' => $user->id]);
    }

    public function test_cleanup_rejects_paths_outside_the_support_vault(): void
    {
        foreach (['support', 'support/conversations', 'support/conversations/../../secrets', 'support/conversations/'.Str::uuid().'/../../file'] as $path) {
            try {
                app(PrivateFileDeletion::class)->schedule($path, true);
                $this->fail('Unsafe cleanup accepted');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_validation_and_honeypot_reject_empty_or_oversized_messages(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/v1/support/chat/conversations', $this->payload(['message' => '   ', 'privacy_acknowledged' => false]))->assertUnprocessable();
        $this->postJson('/api/v1/support/chat/conversations', $this->payload(['message' => str_repeat('a', 5001)]))->assertUnprocessable();
        $this->postJson('/api/v1/support/chat/conversations', $this->payload(['company_website' => 'spam']))->assertUnprocessable();
        $this->assertDatabaseCount('support_conversations', 0);
    }

    public function test_chat_storage_does_not_require_a_configured_mail_provider(): void
    {
        config(['mail.default' => 'log']);
        $this->thread(User::factory()->create());
        $this->assertDatabaseCount('support_messages', 1);
    }

    public function test_conversation_and_message_caps_are_enforced_on_the_server(): void
    {
        $user = User::factory()->create();
        $id = $this->thread($user);
        $thread = SupportConversation::find($id);
        for ($i = 1; $i < 100; $i++) {
            $thread->messages()->create(['client_id' => (string) Str::uuid(), 'body' => 'Test', 'author_role' => 'customer']);
        }
        $this->postJson("/api/v1/support/chat/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'message' => 'Más'])->assertUnprocessable();
        for ($i = 1; $i < 20; $i++) {
            SupportConversation::create(['id' => (string) Str::uuid(), 'creation_key' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Test', 'subject' => 'Test']);
        }
        $this->postJson('/api/v1/support/chat/conversations', $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('support_conversations', 20);
        $this->assertDatabaseCount('support_messages', 100);
    }

    public function test_scanner_outage_never_reports_a_saved_message(): void
    {
        $this->mock(UploadScanner::class)->shouldReceive('scan')->once()->andReturnUsing(fn () => abort(503));
        $this->actingAs(User::factory()->create())->postJson('/api/v1/support/chat/conversations', $this->payload(['attachments' => [$this->file()]]))->assertStatus(503);
        $this->assertDatabaseCount('support_conversations', 0);
        $this->assertDatabaseCount('support_messages', 0);
    }

    public function test_thread_attachment_budget_and_foreign_attachment_ids_are_rejected(): void
    {
        $user = User::factory()->create();
        $id = $this->thread($user);
        $message = SupportConversation::find($id)->messages()->first();
        $file = $message->attachments()->create(['id' => (string) Str::uuid(), 'storage_key' => 'test-only', 'filename' => 'adjunto-1.pdf', 'mime_type' => 'application/pdf', 'size' => 20 * 1024 * 1024]);
        $otherId = $this->thread($user);
        $this->get("/api/v1/support/chat/conversations/{$otherId}/attachments/{$file->id}")->assertNotFound();
        $this->postJson("/api/v1/support/chat/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'message' => 'Más archivos', 'attachments' => [$this->file()]])->assertUnprocessable();
        $this->assertDatabaseCount('support_attachments', 1);
    }

    public function test_private_chat_requires_login_and_a_guest_can_receive_a_human_reply(): void
    {
        $this->getJson('/api/v1/support/chat/conversations')->assertUnauthorized();
        $guest = str_repeat('c', 64);
        $id = $this->withSession(['support_guest_key' => $guest])->postJson('/api/v1/public/support/chat/conversations', $this->payload())->assertCreated()->json('id');
        $this->actingAs($this->agent())->postJson("/api/v1/support/team/conversations/{$id}/messages", ['client_id' => (string) Str::uuid(), 'message' => 'Hola, te podemos ayudar.'])->assertCreated();
        $this->withSession(['support_guest_key' => $guest])->getJson("/api/v1/public/support/chat/conversations/{$id}")->assertJsonPath('messages.1.author_role', 'support')->assertJsonPath('messages.1.body', 'Hola, te podemos ayudar.');
    }

    public function test_failure_after_storing_an_upload_rolls_back_the_message_and_cleans_the_file(): void
    {
        SupportAttachment::creating(function () {
            throw new \RuntimeException('Synthetic database failure');
        });
        try {
            $this->actingAs(User::factory()->create())->postJson('/api/v1/support/chat/conversations', $this->payload(['attachments' => [$this->file()]]))->assertStatus(500);
            $this->assertDatabaseCount('support_conversations', 0);
            $this->assertDatabaseCount('support_messages', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            SupportAttachment::flushEventListeners();
        }
    }

    public function test_personal_access_tokens_cannot_bypass_the_staff_browser_login(): void
    {
        $user = $this->agent();
        $token = $user->createToken('synthetic-test')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/support/team/conversations')->assertForbidden();
    }
}
