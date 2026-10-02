<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Identity\Models\LegalAcceptance;
use App\Domain\Identity\Services\LegalEvidence;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegalEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        config(['services.openai.key' => 'synthetic-test-key']);
        Notification::fake();
        Http::preventStrayRequests();
        Storage::fake('local');
    }

    private function owner(): User
    {
        $user = User::factory()->create(['email' => 'synthetic@example.test', 'password' => 'testpass123']);
        $portfolio = Portfolio::create(['name' => 'Sintética', 'plan' => 'founder']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $this->actingAs($user);

        return $user;
    }

    private function activate(): void
    {
        $this->postJson('/api/v1/assistant/activation', ['accepted' => true, 'notice_version' => config('assistant.notice_version')])->assertOk();
    }

    public function test_registration_requires_explicit_current_acceptance_and_records_encrypted_evidence(): void
    {
        $payload = ['name' => 'Sintética', 'email' => 'registration@example.test', 'password' => 'testpass123', 'password_confirmation' => 'testpass123'];
        $this->postJson('/api/v1/auth/register', [...$payload, 'terms_accepted' => false, 'terms_version' => config('legal.terms_version')])->assertUnprocessable();
        $this->postJson('/api/v1/auth/register', [...$payload, 'terms_accepted' => true, 'terms_version' => 'obsolete'])->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('legal_acceptances', 0);
        $response = $this->postJson('/api/v1/auth/register', [...$payload, 'terms_accepted' => true, 'terms_version' => config('legal.terms_version')])->assertCreated();
        $proof = LegalAcceptance::sole();
        $this->assertSame('terms', $proof->scope);
        $this->assertSame('accepted', $proof->action);
        $this->assertSame(config('legal.terms_version'), $proof->version);
        $this->assertSame($response->json('user.id'), $proof->user_id);
        $this->assertSame($payload['email'], $proof->subject_email);
        $this->assertNull($proof->expires_at);
        $this->assertStringNotContainsString($payload['email'], DB::table('legal_acceptances')->value('subject_email'));
        $this->assertArrayNotHasKey('subject_email', $proof->toArray());
        $response->assertJsonMissingPath('user.legal_acceptances');
        Http::assertNothingSent();
    }

    public function test_previous_beta_terms_are_not_accepted_for_a_new_registration(): void
    {
        $this->assertNotSame('2026-10-01-r2', config('legal.terms_version'));
        $payload = ['name' => 'Sintética', 'email' => 'new-terms@example.test',
            'password' => 'testpass123', 'password_confirmation' => 'testpass123',
            'terms_accepted' => true, 'terms_version' => '2026-10-01-r2'];
        $this->postJson('/api/v1/auth/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('terms_version');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('legal_acceptances', 0);
        $this->postJson('/api/v1/auth/register', [...$payload, 'terms_version' => config('legal.terms_version')])->assertCreated();
        $this->assertSame(config('legal.terms_version'), LegalAcceptance::sole()->version);
        Http::assertNothingSent();
    }

    public function test_assistant_acceptance_is_idempotent_and_rejected_requests_leave_no_evidence(): void
    {
        $user = $this->owner();
        $this->postJson('/api/v1/assistant/activation', ['accepted' => false, 'notice_version' => config('assistant.notice_version')])->assertUnprocessable();
        $this->postJson('/api/v1/assistant/activation', ['accepted' => true, 'notice_version' => 'old'])->assertUnprocessable();
        $this->assertDatabaseCount('legal_acceptances', 0);
        $this->activate();
        $acceptedAt = $user->fresh()->assistant_enabled_at;
        $this->travel(1)->hours();
        $this->activate();
        $this->assertDatabaseCount('legal_acceptances', 1);
        $this->assertSame($acceptedAt, $user->fresh()->assistant_enabled_at);
    }

    public function test_withdrawal_deletes_content_but_retains_only_bounded_evidence_and_reactivation_history(): void
    {
        $user = $this->owner();
        $this->activate();
        $chat = AiConversation::create(['user_id' => $user->id, 'portfolio_id' => $user->portfolio()->id]);
        $chat->messages()->create(['role' => 'user', 'content' => 'synthetic-private-content']);
        $this->travel(1)->days();
        $this->deleteJson('/api/v1/assistant/activation')->assertNoContent();
        $expiry = now()->addDays(config('legal.evidence_retention_days'));
        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);
        $this->assertDatabaseCount('legal_acceptances', 2);
        $this->assertNull($user->fresh()->assistant_notice_version);
        foreach (LegalAcceptance::all() as $proof) {
            $this->assertTrue($proof->expires_at->equalTo($expiry));
            $this->assertStringNotContainsString('synthetic-private-content', json_encode($proof->getAttributes()));
        }
        $this->travel(1)->days();
        $this->deleteJson('/api/v1/assistant/activation')->assertNoContent();
        $this->assertDatabaseCount('legal_acceptances', 2);
        $this->assertTrue(LegalAcceptance::first()->expires_at->equalTo($expiry));
        $this->activate();
        $this->assertDatabaseCount('legal_acceptances', 3);
        $this->assertNull(LegalAcceptance::latest('id')->first()->expires_at);
        Http::assertNothingSent();
    }

    public function test_new_notice_preserves_old_version_and_no_legacy_acceptances_are_invented(): void
    {
        $user = $this->owner();
        $user->forceFill(['assistant_enabled_at' => now()->subYear(), 'assistant_notice_version' => 'legacy'])->save();
        $this->assertDatabaseCount('legal_acceptances', 0);
        $this->activate();
        $originalVersion = config('assistant.notice_version');
        $this->travel(1)->days();
        config(['assistant.notice_version' => 'test-next-notice']);
        $this->activate();
        $this->assertDatabaseCount('legal_acceptances', 2);
        $this->assertSame($originalVersion, LegalAcceptance::first()->version);
        $this->assertNotNull(LegalAcceptance::first()->expires_at);
        $this->assertSame('test-next-notice', LegalAcceptance::latest('id')->first()->version);
        $this->assertNull(LegalAcceptance::latest('id')->first()->expires_at);
    }

    public function test_evidence_and_activation_fail_atomically(): void
    {
        $user = $this->owner();
        DB::listen(function (QueryExecuted $query) {
            if (str_starts_with($query->sql, 'insert into "legal_acceptances"')) {
                throw new \RuntimeException('synthetic-storage-failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->activate();
            $this->fail('Activation must not succeed without its evidence.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('synthetic-storage-failure', $exception->getMessage());
        }
        $this->assertNull($user->fresh()->assistant_enabled_at);
        $this->assertDatabaseCount('legal_acceptances', 0);
    }

    public function test_document_simulation_has_separate_idempotent_evidence_and_chat_withdrawal_covers_both(): void
    {
        $this->app->instance('env', 'local');
        $user = $this->owner();
        $user->forceFill(['role' => 'admin', 'two_factor_confirmed_at' => now()])->save();
        $this->activate();
        $payload = ['accepted' => true, 'notice_version' => config('ai_documents.notice_version')];
        $this->postJson('/api/v1/document-ai/consent', $payload)->assertOk();
        $this->postJson('/api/v1/document-ai/consent', $payload)->assertOk();
        $this->assertDatabaseCount('legal_acceptances', 2);
        $this->deleteJson('/api/v1/assistant/activation')->assertNoContent();
        $this->assertDatabaseCount('legal_acceptances', 4);
        $this->assertDatabaseHas('legal_acceptances', ['scope' => 'document_ai', 'action' => 'withdrawn', 'version' => $payload['notice_version']]);
        $this->assertNull($user->fresh()->document_ai_accepted_at);
        $this->assertDatabaseCount('ai_document_extractions', 0);
        Http::assertNothingSent();
    }

    public function test_document_revocation_keeps_chat_active_and_prune_does_not_remove_active_proof(): void
    {
        $this->app->instance('env', 'local');
        $user = $this->owner();
        $user->forceFill(['role' => 'admin', 'two_factor_confirmed_at' => now()])->save();
        $this->activate();
        $this->postJson('/api/v1/document-ai/consent', ['accepted' => true, 'notice_version' => config('ai_documents.notice_version')])->assertOk();
        $this->deleteJson('/api/v1/document-ai/consent')->assertNoContent();
        $this->assertNotNull($user->fresh()->assistant_enabled_at);
        $this->artisan('legal:prune')->assertSuccessful();
        $this->assertDatabaseCount('legal_acceptances', 3);
        $this->travel(config('legal.evidence_retention_days'))->days();
        $this->artisan('legal:prune')->assertSuccessful();
        $this->assertDatabaseCount('legal_acceptances', 1);
        $this->assertSame('assistant', LegalAcceptance::sole()->scope);
    }

    public function test_deleting_account_preserves_restricted_evidence_with_expiry_not_its_portfolio_or_chat(): void
    {
        $user = $this->owner();
        DB::transaction(function () use ($user) {
            app(LegalEvidence::class)->accept(User::whereKey($user->id)->lockForUpdate()->firstOrFail(), 'terms', config('legal.terms_version'));
        });
        $this->activate();
        $this->deleteJson('/api/v1/account', ['current_password' => 'testpass123', 'confirmation' => 'ELIMINAR'])->assertNoContent();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('portfolios', 0);
        $this->assertDatabaseCount('legal_acceptances', 3);
        foreach (LegalAcceptance::all() as $proof) {
            $this->assertNull($proof->user_id);
            $this->assertNotNull($proof->expires_at);
            $this->assertSame('synthetic@example.test', $proof->subject_email);
        }
        $this->travel(config('legal.evidence_retention_days'))->days();
        $this->artisan('legal:prune')->assertSuccessful();
        $this->assertDatabaseCount('legal_acceptances', 0);
    }
}
