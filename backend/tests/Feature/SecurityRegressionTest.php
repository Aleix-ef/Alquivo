<?php

namespace Tests\Feature;

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Services\AssistantUsageService;
use App\Domain\Assistant\Services\PortfolioAssistantTools;
use App\Domain\Documents\Services\PrivateFileDeletion;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use App\Support\ProductionReadiness;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_referrer_policy_preserves_same_origin_cookie_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()
            ->assertHeader('Referrer-Policy', 'same-origin');
    }

    private function owner(bool $verified = true): array
    {
        $user = User::factory()->create(['password' => 'oldpass123', 'email_verified_at' => $verified ? now() : null]);
        $portfolio = Portfolio::create(['name' => 'Cartera', 'plan' => 'founder', 'trial_ends_at' => null]);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        return [$user, $portfolio];
    }

    public function test_email_change_requires_reauthentication_and_case_cannot_bypass_uniqueness(): void
    {
        Notification::fake();
        [$user] = $this->owner();
        $data = ['name' => $user->name, 'email' => 'new@example.com', 'portfolio_name' => 'Cartera', 'country_code' => 'ES', 'currency' => 'EUR'];
        $this->actingAs($user)->putJson('/api/v1/account', $data)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame($user->email, $user->fresh()->email);
        User::factory()->create(['email' => 'used@example.com']);
        $this->putJson('/api/v1/account', [...$data, 'email' => 'USED@example.com', 'current_password' => 'oldpass123'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->putJson('/api/v1/account', [...$data, 'current_password' => 'oldpass123'])->assertOk()->assertJsonPath('user.email_verified_at', null);
        $this->getJson('/api/v1/properties')->assertOk();
    }

    public function test_unverified_owner_can_use_own_portfolio_but_not_support_team_or_billing(): void
    {
        Notification::fake();
        [$user, $portfolio] = $this->owner(false);
        $own = $portfolio->properties()->create(['name' => 'Mío', 'type' => 'housing', 'address_line' => 'Calle Propia']);
        [, $otherPortfolio] = $this->owner();
        $foreign = $otherPortfolio->properties()->create(['name' => 'Ajeno', 'type' => 'housing', 'address_line' => 'Calle Ajena']);
        $this->actingAs($user)->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('properties.0.id', $own->id);
        $this->getJson('/api/v1/properties')->assertOk()->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/v1/properties/'.$foreign->id)->assertNotFound();
        $this->getJson('/api/v1/documents')->assertOk();
        $this->getJson('/api/v1/assistant/conversations')->assertOk();
        $this->getJson('/api/v1/support/team/conversations')->assertForbidden();
        $this->postJson('/api/v1/billing/checkout', [])->assertForbidden();
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->postJson('/api/v1/auth/email/resend')->assertOk();
    }

    public function test_login_attempts_are_limited_by_account_even_across_ips(): void
    {
        [$user] = $this->owner();
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$i])->postJson('/api/v1/auth/login', ['email' => strtoupper($user->email), 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.6'])->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'oldpass123'])->assertStatus(429);
    }

    public function test_reset_revokes_database_sessions_and_tokens(): void
    {
        [$user] = $this->owner();
        $user->createToken('test');
        DB::table('sessions')->insert(['id' => 'old-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        config(['session.driver' => 'database']);
        $token = Password::createToken($user);
        $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'old-device']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_webhook_fails_closed_without_secret_and_rejects_invalid_signature(): void
    {
        config(['cashier.webhook.secret' => null]);
        $this->postJson('/stripe/webhook', ['type' => 'customer.subscription.updated'])->assertStatus(503);
        config(['cashier.webhook.secret' => 'whsec_test']);
        $this->postJson('/stripe/webhook', ['type' => 'customer.subscription.updated'])->assertStatus(403);
    }

    public function test_ai_activation_is_required_and_revoking_it_deletes_history_without_resetting_quota(): void
    {
        [$user, $portfolio] = $this->owner();
        Http::fake();
        $this->actingAs($user)->postJson('/api/v1/assistant/conversations')->assertForbidden();
        $this->postJson('/api/v1/assistant/activation', ['accepted' => true, 'notice_version' => 'old'])->assertUnprocessable();
        $this->postJson('/api/v1/assistant/activation', ['accepted' => true, 'notice_version' => config('assistant.notice_version')])->assertOk();
        $id = $this->postJson('/api/v1/assistant/conversations')->assertCreated()->json('id');
        AiConversation::findOrFail($id)->messages()->create(['role' => 'user', 'content' => 'private']);
        app(AssistantUsageService::class)->reserve($portfolio, $user);
        $this->deleteJson('/api/v1/assistant/activation')->assertNoContent();
        $this->assertDatabaseCount('ai_messages', 0);
        $this->assertSame(1, app(AssistantUsageService::class)->summary($portfolio, $user)['used']);
        $this->postJson('/api/v1/assistant/conversations')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_failed_ai_requests_consume_quota_and_deleting_chat_cannot_restore_it(): void
    {
        [$user, $portfolio] = $this->owner();
        $user->forceFill(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')])->save();
        config(['services.openai.key' => 'test-key', 'assistant.limits.founder' => 1, 'ai.fallback_profile' => null]);
        Http::fake(['*' => Http::response(['error' => ['message' => 'secret-provider-internals']], 500)]);
        $this->actingAs($user);
        $id = $this->postJson('/api/v1/assistant/conversations')->json('id');
        $this->postJson("/api/v1/assistant/conversations/$id/messages", ['message' => 'Hola'])->assertStatus(502)->assertDontSee('secret-provider-internals')->assertJsonPath('usage.used', 1);
        $this->deleteJson("/api/v1/assistant/conversations/$id")->assertNoContent();
        $id = $this->postJson('/api/v1/assistant/conversations')->json('id');
        $this->postJson("/api/v1/assistant/conversations/$id/messages", ['message' => 'Otra'])->assertUnprocessable();
        Http::assertSentCount(1);
    }

    public function test_in_flight_ai_request_blocks_duplicate_requests_and_history_deletion(): void
    {
        [$user, $portfolio] = $this->owner();
        $user->forceFill(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')])->save();
        $chat = AiConversation::create(['user_id' => $user->id, 'portfolio_id' => $portfolio->id]);
        $lock = Cache::lock('assistant-user:'.$user->id, 90);
        $lock->get();
        Http::fake();
        $this->actingAs($user)->postJson("/api/v1/assistant/conversations/$chat->id/messages", ['message' => 'Hola'])->assertStatus(409);
        $this->deleteJson("/api/v1/assistant/conversations/$chat->id")->assertStatus(409);
        Http::assertNothingSent();
        $lock->release();
    }

    public function test_ai_tools_scope_data_and_exclude_exact_address(): void
    {
        [$user, $portfolio] = $this->owner();
        [, $otherPortfolio] = $this->owner();
        $property = $otherPortfolio->properties()->create(['name' => 'Otro', 'type' => 'housing', 'address_line' => 'Secret Street 1']);
        $tools = app(PortfolioAssistantTools::class);
        $this->assertFalse($tools->execute($portfolio, 'get_property_details', ['property_id' => $property->id])['found']);
        $result = $tools->execute($otherPortfolio, 'get_property_details', ['property_id' => $property->id]);
        $this->assertStringNotContainsString('Secret Street', json_encode($result));
        $this->expectException(\InvalidArgumentException::class);
        $tools->execute($portfolio, 'get_portfolio_overview', ['portfolio_id' => $otherPortfolio->id]);
    }

    public function test_retention_removes_old_messages_but_preserves_current_month_usage(): void
    {
        [$user, $portfolio] = $this->owner();
        $chat = AiConversation::create(['user_id' => $user->id, 'portfolio_id' => $portfolio->id, 'created_at' => now()->subDays(31), 'updated_at' => now()->subDays(31)]);
        $chat->messages()->create(['role' => 'user', 'content' => 'private', 'created_at' => now()->subDays(31)]);
        app(AssistantUsageService::class)->reserve($portfolio, $user);
        $this->artisan('assistant:prune')->assertSuccessful();
        $this->assertDatabaseCount('ai_messages', 0);
        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertSame(1, app(AssistantUsageService::class)->summary($portfolio, $user)['used']);
    }

    public function test_sanctum_rejects_a_cookie_session_with_a_previous_password_hash(): void
    {
        config(['sanctum.stateful' => ['localhost']]);
        [$user] = $this->owner();
        $oldHash = Auth::guard('web')->hashPasswordForCookie($user->password);
        $user->update(['password' => 'newpass123']);
        $this->actingAs($user)->withHeader('Origin', 'http://localhost')
            ->withSession(['password_hash_web' => $oldHash])
            ->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_stateful_login_requires_csrf_even_with_valid_credentials(): void
    {
        config(['sanctum.stateful' => ['localhost']]);
        [$user] = $this->owner();
        // Laravel bypasses CSRF in its testing environment; explicitly exercise it.
        $this->app['env'] = 'local';
        $this->withHeader('Origin', 'http://localhost')->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'oldpass123',
        ])->assertStatus(419);
    }

    public function test_production_refuses_insecure_configuration_without_exposing_details(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true, 'session.secure' => false]);
        $this->getJson('/api/v1/public/plans')->assertStatus(503)->assertDontSee('APP_DEBUG');
        $this->artisan('security:check')->assertFailed();
    }

    public function test_production_check_accepts_complete_configuration(): void
    {
        $database = config('database.default');
        $this->app['env'] = 'production';
        config([
            'app.debug' => false, 'app.url' => 'https://app.alquivo.test',
            'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'services.frontend_url' => 'https://app.alquivo.test',
            'sanctum.stateful' => ['app.alquivo.test'],
            'cors.allowed_origins' => ['https://app.alquivo.test'], 'cors.allowed_origins_patterns' => [],
            'session.secure' => true, 'session.http_only' => true, 'session.same_site' => 'lax',
            'session.encrypt' => true, 'session.driver' => 'database',
            'database.default' => 'pgsql', 'database.connections.pgsql.password' => str_repeat('x', 32),
            'cache.default' => 'database', 'queue.default' => 'database',
            'mail.default' => 'smtp', 'mail.from.address' => 'support@alquivo.test',
            'mail.mailers.smtp.host' => 'smtp.alquivo.test', 'mail.mailers.smtp.port' => 587,
            'security.uploads_scan' => true, 'trustedproxy.proxies' => ['127.0.0.1'],
            'cashier.secret' => null, 'assistant.enabled' => false,
        ]);
        try {
            $this->assertSame([], app(ProductionReadiness::class)->failures());
            $this->artisan('security:check')->assertSuccessful();
        } finally {
            config(['database.default' => $database]);
        }
    }

    public function test_unavailable_scanner_prevents_storing_documents(): void
    {
        Storage::fake('local');
        [$user] = $this->owner();
        config(['security.uploads_scan' => true, 'security.clamav_host' => '127.0.0.1', 'security.clamav_port' => 1]);
        $file = UploadedFile::fake()->createWithContent('test.pdf', "%PDF-1.4\n%%EOF");
        $this->actingAs($user)->postJson('/api/v1/documents', ['file' => $file, 'category' => 'other'])->assertStatus(503);
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failed_file_deletion_is_retried_after_the_document_is_gone(): void
    {
        $disk = Storage::fake('local');
        $path = 'portfolios/1/documents/test.pdf';
        $disk->put($path, 'private');
        $service = app(PrivateFileDeletion::class);
        $id = $service->schedule($path);
        Storage::shouldReceive('disk')->with('local')->andThrow(new \RuntimeException('offline'));
        $this->assertFalse($service->process($id));
        $this->assertDatabaseHas('private_file_deletions', ['id' => $id]);
        Storage::swap(new FilesystemManager($this->app));
        Storage::set('local', $disk);
        $this->assertTrue($service->process($id));
        $disk->assertMissing($path);
        $this->assertDatabaseCount('private_file_deletions', 0);
    }

    public function test_private_responses_are_not_cacheable(): void
    {
        [$user] = $this->owner();
        $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
