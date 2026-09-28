<?php

namespace Tests\Feature;

use App\Domain\Support\Models\SupportConversation;
use App\Models\User;
use App\Notifications\SupportTicketNotification;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportTicketNotificationTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        // The test database is SQLite in memory. A rollback migration is both
        // unnecessary and incompatible with an older one-way column migration.
        // We need real commits so afterCommit notifications are observable.
        RefreshDatabaseState::$migrated = false;
        $this->artisan('migrate:fresh');
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost'], 'mail.default' => 'smtp', 'support.email' => 'soporte@alquivo.com']);
        $this->withHeader('Origin', 'http://localhost');
        Storage::fake('local');
        Notification::fake();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'conversation_id' => (string) Str::uuid(),
            'client_id' => (string) Str::uuid(),
            'subject' => 'Datos personales que no deben ir en el correo',
            'message' => 'Contenido privado del cliente',
            'privacy_acknowledged' => true,
        ], $overrides);
    }

    public function test_each_new_customer_message_notifies_once_after_commit_without_emailing_content(): void
    {
        $customer = User::factory()->create();
        $first = $this->payload();
        $ticket = $this->actingAs($customer)->postJson('/api/v1/support/chat/conversations', $first)
            ->assertCreated()->json('id');
        Notification::assertSentOnDemandTimes(SupportTicketNotification::class, 1);

        $this->postJson('/api/v1/support/chat/conversations', $first)->assertCreated();
        Notification::assertSentOnDemandTimes(SupportTicketNotification::class, 1);

        $this->postJson("/api/v1/support/chat/conversations/{$ticket}/messages", [
            'client_id' => (string) Str::uuid(), 'message' => 'Seguimiento privado',
        ])->assertCreated();
        Notification::assertSentOnDemandTimes(SupportTicketNotification::class, 2);
        $sent = Notification::sent(new AnonymousNotifiable, SupportTicketNotification::class)->values();
        $this->assertTrue($sent[0]->newTicket);
        $this->assertFalse($sent[1]->newTicket);
        $mail = $sent[0]->toMail(new AnonymousNotifiable)->toArray();
        $serialized = json_encode($mail);
        $this->assertStringNotContainsString('Datos personales', $serialized);
        $this->assertStringNotContainsString('Contenido privado', $serialized);
        $this->assertStringContainsString('/support/inbox', $mail['actionUrl']);

        $agent = User::factory()->create(['two_factor_confirmed_at' => now()]);
        DB::table('support_agents')->insert(['user_id' => $agent->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($agent)->postJson("/api/v1/support/team/conversations/{$ticket}/messages", [
            'client_id' => (string) Str::uuid(), 'message' => 'Respuesta del equipo',
        ])->assertCreated();
        Notification::assertSentOnDemandTimes(SupportTicketNotification::class, 2);
    }

    public function test_tickets_are_saved_without_mail_and_closed_history_does_not_block_new_open_tickets(): void
    {
        config(['mail.default' => 'log']);
        $customer = User::factory()->create();
        $first = $this->actingAs($customer)->postJson('/api/v1/support/chat/conversations', $this->payload())
            ->assertCreated()->json('id');
        Notification::assertNothingSent();
        $this->postJson("/api/v1/support/chat/conversations/{$first}/close")->assertOk();
        $second = $this->postJson('/api/v1/support/chat/conversations', $this->payload())
            ->assertCreated()->json('id');
        $this->assertNotSame($first, $second);
        $this->getJson('/api/v1/support/chat/conversations?status=open')
            ->assertOk()->assertJsonCount(1, 'conversations')->assertJsonPath('conversations.0.id', $second);
        $this->getJson('/api/v1/support/chat/conversations?status=closed')
            ->assertOk()->assertJsonCount(1, 'conversations')->assertJsonPath('conversations.0.id', $first);
        $this->assertDatabaseCount('support_conversations', 2);
        $this->assertSame('waiting_support', SupportConversation::findOrFail($second)->status);

        for ($i = 0; $i < 19; $i++) {
            SupportConversation::create([
                'id' => (string) Str::uuid(), 'creation_key' => (string) Str::uuid(),
                'user_id' => $customer->id, 'name' => 'Cliente', 'subject' => 'Otro ticket',
            ]);
        }
        $this->postJson("/api/v1/support/chat/conversations/{$first}/messages", [
            'client_id' => (string) Str::uuid(), 'message' => 'Reabrir',
        ])->assertUnprocessable();
        $this->assertSame('closed', SupportConversation::findOrFail($first)->status);
    }
}
