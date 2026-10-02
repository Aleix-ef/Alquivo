<?php

namespace Tests\Feature;

use App\Domain\Assistant\Contracts\AIProviderInterface;
use App\Domain\Assistant\Models\AiActionProposal;
use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Providers\OpenAIProvider;
use App\Domain\Assistant\Services\PortfolioAssistantTools;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ProductionAiBudgetProvider;
use Tests\TestCase;

class AssistantFinancialHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 2)->startOfDay());
        Http::preventStrayRequests();
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')]);
        $portfolio = Portfolio::create(['name' => 'Cartera sintética', 'plan' => 'founder', 'trial_ends_at' => null]);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'San Nicolás', 'city' => 'Valencia', 'type' => 'housing', 'address_line' => 'Dirección ficticia']);
        foreach ([
            ['2020-01-10', 'income', 'rent', '1100.00', 'paid'],
            ['2026-09-05', 'income', 'rent', '550.00', 'paid'],
            ['2026-10-01', 'income', 'rent', '550.00', 'paid'],
            ['2026-09-15', 'expense', 'maintenance', '100.25', 'paid'],
            ['2026-10-01', 'expense', 'insurance', '40.10', 'paid'],
            ['2026-09-01', 'income', 'rent', '800.00', 'pending'],
            ['2026-10-01', 'expense', 'tax', '80.00', 'pending'],
            ['2026-09-01', 'income', 'other', '900.00', 'cancelled'],
            ['2027-01-01', 'income', 'rent', '9999.00', 'paid'],
        ] as [$date, $direction, $category, $amount, $status]) {
            Transaction::create([
                'portfolio_id' => $portfolio->id, 'property_id' => $property->id,
                'direction' => $direction, 'category' => $category, 'description' => 'Descripción privada de prueba',
                'amount' => $amount, 'transaction_date' => $date, 'status' => $status, 'notes' => 'Nota privada de prueba',
            ]);
        }
        $conversation = AiConversation::create(['portfolio_id' => $portfolio->id, 'user_id' => $user->id]);

        return compact('user', 'portfolio', 'property', 'conversation');
    }

    public function test_historical_totals_include_old_records_and_separate_collected_income_from_net(): void
    {
        ['portfolio' => $portfolio, 'property' => $property] = $this->fixture();
        $otherProperty = $portfolio->properties()->create(['name' => 'Otro piso', 'type' => 'housing', 'address_line' => 'Ficticia']);
        Transaction::create(['portfolio_id' => $portfolio->id, 'property_id' => $otherProperty->id,
            'direction' => 'income', 'category' => 'rent', 'description' => 'Otro piso', 'amount' => 7000,
            'transaction_date' => '2015-01-01', 'status' => 'paid']);

        $result = app(PortfolioAssistantTools::class)->execute($portfolio, 'get_financial_summary', [
            'time_scope' => 'all_time', 'from' => null, 'to' => null, 'property_id' => $property->id,
        ]);

        $this->assertSame('all_time', $result['time_scope']);
        $this->assertSame('2020-01-10', $result['from']);
        $this->assertSame('2026-10-02', $result['to']);
        $this->assertSame(2200.0, $result['income']);
        $this->assertSame(140.35, $result['expenses']);
        $this->assertSame(2059.65, $result['net']);
        $this->assertSame(8, $result['recorded_transaction_count']);
        $this->assertSame(5, $result['recorded_paid_count']);
        $this->assertSame(2200.0, $result['income_by_category']['rent']);
        $this->assertSame(800.0, $result['other_unpaid_transactions']['income']);
        $this->assertSame(80.0, $result['other_unpaid_transactions']['expenses']);
        $this->assertStringNotContainsString('privada de prueba', json_encode($result));
        Http::assertNothingSent();
    }

    public function test_monthly_default_and_explicit_date_range_keep_their_existing_meaning(): void
    {
        ['portfolio' => $portfolio, 'property' => $property] = $this->fixture();
        $tools = app(PortfolioAssistantTools::class);
        $monthly = $tools->execute($portfolio, 'get_financial_summary', ['property_id' => $property->id]);
        $this->assertSame('period', $monthly['time_scope']);
        $this->assertSame('2026-10-01', $monthly['from']);
        $this->assertSame(550.0, $monthly['income']);
        $this->assertSame(509.9, $monthly['net']);
        $september = $tools->execute($portfolio, 'get_financial_summary', [
            'time_scope' => 'period', 'from' => '2026-09-01', 'to' => '2026-09-30', 'property_id' => $property->id,
        ]);
        $this->assertSame(550.0, $september['income']);
        $this->assertSame(449.75, $september['net']);
    }

    public function test_historical_totals_cannot_read_a_foreign_property_or_portfolio(): void
    {
        ['portfolio' => $portfolio] = $this->fixture();
        $other = Portfolio::create(['name' => 'Ajena']);
        $foreign = $other->properties()->create(['name' => 'San Nicolás', 'type' => 'housing', 'address_line' => 'Ajena']);
        Transaction::create(['portfolio_id' => $other->id, 'property_id' => $foreign->id,
            'direction' => 'income', 'category' => 'rent', 'description' => 'Solo otra cartera', 'amount' => 999999,
            'transaction_date' => '2010-01-01', 'status' => 'paid']);
        $tools = app(PortfolioAssistantTools::class);
        $this->assertFalse($tools->execute($portfolio, 'get_financial_summary', [
            'time_scope' => 'all_time', 'property_id' => $foreign->id,
        ])['found']);
        $own = $tools->execute($portfolio, 'get_financial_summary', ['time_scope' => 'all_time']);
        $this->assertSame(2200.0, $own['income']);
        $this->assertSame('2020-01-10', $own['from']);
        $this->assertStringNotContainsString('999999', json_encode($own));
    }

    public function test_empty_history_explicitly_has_no_records_or_start_date(): void
    {
        $portfolio = Portfolio::create(['name' => 'Vacía']);
        $result = app(PortfolioAssistantTools::class)->execute($portfolio, 'get_financial_summary', ['time_scope' => 'all_time']);
        $this->assertNull($result['from']);
        $this->assertSame(0, $result['recorded_transaction_count']);
        $this->assertSame(0, $result['recorded_paid_count']);
        $this->assertSame(0.0, $result['income']);
        $this->assertSame(0.0, $result['net']);
        $this->assertStringContainsString('Sin registros no implica ausencia', $result['basis']);
    }

    #[DataProvider('invalidScopes')]
    public function test_conflicting_or_invalid_time_scopes_are_rejected(array $arguments): void
    {
        $portfolio = Portfolio::create(['name' => 'Cartera']);
        $this->expectException(\InvalidArgumentException::class);
        try {
            app(PortfolioAssistantTools::class)->execute($portfolio, 'get_financial_summary', $arguments);
        } catch (ValidationException $exception) {
            throw new \InvalidArgumentException('Invalid scope.', previous: $exception);
        }
    }

    public static function invalidScopes(): array
    {
        return [
            'invalid scope' => [['time_scope' => 'unlimited']],
            'history plus date' => [['time_scope' => 'all_time', 'from' => '2026-01-01']],
            'history plus end' => [['time_scope' => 'all_time', 'to' => '2026-09-30']],
            'reversed dates' => [['time_scope' => 'period', 'from' => '2026-10-02', 'to' => '2026-10-01']],
            'oversized date range' => [['time_scope' => 'period', 'from' => '2020-01-01', 'to' => '2026-10-02']],
        ];
    }

    public function test_follow_up_uses_user_history_but_refreshes_financial_evidence_without_proposals(): void
    {
        config(['services.openai.key' => 'test-key']);
        ['user' => $user, 'portfolio' => $portfolio, 'property' => $property, 'conversation' => $conversation] = $this->fixture();
        $conversation->messages()->create(['role' => 'user', 'content' => 'cuanto he cobrado de san nicolas']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'En San Nicolás has cobrado 550 € este mes.']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'cuanto dinero he ganado en total con san nicolas']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => '¿De qué mensualidad se trata? Indícame el mes y el año del alquiler.']);
        Http::fakeSequence()
            ->push(['output' => [['type' => 'function_call', 'call_id' => 'property', 'name' => 'search_properties', 'arguments' => json_encode(['query' => 'San Nicolás'])]]])
            ->push(['output' => [['type' => 'function_call', 'call_id' => 'history', 'name' => 'get_financial_summary', 'arguments' => json_encode([
                'time_scope' => 'all_time', 'from' => null, 'to' => null, 'property_id' => $property->id,
            ])]]])
            ->push(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'kind' => 'answer', 'basis' => 'portfolio_data',
                'content' => 'Según todo el histórico registrado de San Nicolás, has cobrado 2.200 €, pagado 140,35 € de gastos y obtenido un neto de 2.059,65 €. No es el beneficio fiscal.',
            ])]]]]]);

        $this->actingAs($user)->postJson('/api/v1/assistant/conversations/'.$conversation->id.'/messages', [
            'message' => 'en total con el piso',
        ])->assertOk()->assertJsonPath('assistant_message.metadata.kind', 'answer')
            ->assertJsonPath('assistant_message.metadata.sources.1.path', '/finance');

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => collect($request['input'])->contains(fn ($item) => ($item['role'] ?? null) === 'user' && $item['content'] === 'cuanto he cobrado de san nicolas'));
        Http::assertSent(fn ($request) => collect($request['input'])->contains(function ($item) {
            if (($item['type'] ?? null) !== 'function_call_output' || $item['call_id'] !== 'history') {
                return false;
            }
            $result = json_decode($item['output'], true);

            return $result['time_scope'] === 'all_time' && $result['income'] === 2200 && $result['net'] === 2059.65;
        }));
        $this->assertDatabaseCount('ai_action_proposals', 0);
        $this->assertDatabaseCount('transactions', 9);
        $this->assertSame('0.00', app(PortfolioAssistantTools::class)->execute($portfolio, 'get_financial_summary', ['time_scope' => 'all_time'])['rent_charges_pending_all_periods']['remaining_amount']);
    }

    public function test_live_synthetic_history_queries_with_a_persistent_two_cent_budget(): void
    {
        if (getenv('ALQUIVO_LIVE_FINANCIAL_HISTORY') !== 'YES') {
            $this->markTestSkipped('Explicit synthetic history evaluation opt-in required.');
        }
        $this->assertTrue(app()->environment('testing'));
        $this->assertNotEmpty(config('services.openai.key'));
        $this->assertSame('fast', config('ai.routing.chat'));
        $this->assertSame('https://api.openai.com/v1', config('assistant.base_url'));
        Http::allowStrayRequests(['https://api.openai.com/v1/responses']);
        $provider = new ProductionAiBudgetProvider(app(OpenAIProvider::class), storage_path('app/ai-financial-history-20261002-budget.json'), 20_000_000);
        $this->app->instance(AIProviderInterface::class, $provider);
        $results = [];
        $path = storage_path('app/ai-financial-history-20261002-report.json');
        // Refuse to overwrite results or accidentally repeat a paid run.
        $this->assertFileDoesNotExist($path);
        foreach ([
            ['total_net', 'cuanto dinero he ganado en total con san nicolas', false],
            ['follow_up_1', 'en total con el piso', true],
            ['follow_up_2', 'en total con el piso', true],
            ['follow_up_3', 'en total con el piso', true],
            ['collected_without_month', 'cuanto he cobrado de san nicolas', false],
        ] as [$id, $prompt, $followUp]) {
            ['user' => $user, 'property' => $property, 'conversation' => $conversation] = $this->fixture();
            if ($followUp) {
                $conversation->messages()->create(['role' => 'user', 'content' => 'cuanto he cobrado de san nicolas']);
                $conversation->messages()->create(['role' => 'assistant', 'content' => 'En San Nicolás has cobrado 550 € este mes.']);
                $conversation->messages()->create(['role' => 'user', 'content' => 'cuanto dinero he ganado en total con san nicolas']);
                $conversation->messages()->create(['role' => 'assistant', 'content' => '¿De qué mensualidad se trata? Indícame el mes y el año del alquiler.']);
            }
            $provider->startCase($id);
            $start = microtime(true);
            $response = $this->actingAs($user)->postJson('/api/v1/assistant/conversations/'.$conversation->id.'/messages', ['message' => $prompt]);
            $run = AiRun::where('conversation_id', $conversation->id)->latest('id')->firstOrFail();
            $content = (string) $response->json('assistant_message.content');
            $normalized = str_replace(',', '.', preg_replace('/(?<=\d)[ .\x{00a0}\x{202f}](?=\d{3}(?:\D|$))/u', '', $content));
            $history = collect($provider->trace())->firstWhere('name', 'get_financial_summary');
            $correctScope = ($history['arguments']['time_scope'] ?? null) === 'all_time'
                && ($history['arguments']['property_id'] ?? null) === $property->id;
            $correctAmounts = str_contains($normalized, '2200')
                && (str_contains($normalized, '2059.65') || $id === 'collected_without_month');
            $hasProposal = AiActionProposal::where('run_id', $run->id)->exists();
            $isAnswer = $response->json('assistant_message.metadata.kind') === 'answer';
            $classification = $hasProposal || ($isAnswer && (! $correctScope || ! $correctAmounts)) ? 'DANGEROUS FAILURE'
                : ($response->status() === 200 && $correctScope && $correctAmounts && $isAnswer ? 'PASS' : 'SAFE FAILURE');
            $results[] = [
                'id' => $id, 'prompt' => $prompt, 'synthetic_history' => $followUp,
                'tools' => $provider->trace(), 'response' => $content, 'http_status' => $response->status(),
                'model' => $response->json('assistant_message.model'), 'input_tokens' => $run->input_tokens,
                'output_tokens' => $run->output_tokens, 'cost_usd' => $run->estimated_cost_nano_usd / 1_000_000_000,
                'latency_ms' => (int) ((microtime(true) - $start) * 1000), 'auto_classification' => $classification,
                'human_classification' => null, 'human_comment' => '',
            ];
            file_put_contents($path, json_encode(['synthetic_only' => true, 'budget_usd' => 0.02,
                'cumulative_reserved_or_spent_usd' => $provider->spentNano() / 1_000_000_000,
                'cases' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            fprintf(STDERR, "[Alquivo financial history] %s %s cumulative=%.6f USD\n", $id, $classification, $provider->spentNano() / 1_000_000_000);
            $this->assertFalse($hasProposal);
            $this->assertSame(200, $response->status());
            $this->assertSame('PASS', $classification, $content);
            $this->assertDatabaseCount('transactions', 9 * count($results));
        }
        $this->assertLessThanOrEqual(20_000_000, $provider->spentNano());
    }
}
