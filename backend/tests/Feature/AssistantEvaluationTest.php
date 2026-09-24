<?php

namespace Tests\Feature;

use App\Domain\Assistant\Evaluation\EvaluationGrader;
use App\Domain\Assistant\Evaluation\EvaluationReport;
use App\Domain\Assistant\Evaluation\EvaluationRunner;
use App\Domain\Assistant\Evaluation\FixtureSuite;
use App\Domain\Assistant\Providers\FakeAIProvider;
use App\Domain\Assistant\Services\AIModelRouter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class AssistantEvaluationTest extends TestCase
{
    public function test_reference_replay_checks_all_synthetic_cases_without_database_or_network(): void
    {
        Http::preventStrayRequests();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $results = $this->results('reference');
        $summary = app(EvaluationReport::class)->summarize($results);
        $this->assertSame(9, $summary['cases']);
        $this->assertSame(9, $summary['passed']);
        $this->assertGreaterThan(0, $summary['input_tokens']);
        $this->assertGreaterThan(0, $summary['output_tokens']);
        $this->assertGreaterThan(0, (float) $summary['estimated_cost_usd']);
        $this->assertSame([], $queries);
        Http::assertNothingSent();
    }

    public function test_comparison_detects_a_wrong_amount_and_reports_it_as_a_regression(): void
    {
        $baseline = $this->results('reference');
        $candidate = $this->results('regression');
        $comparison = app(EvaluationReport::class)->compare($baseline, $candidate);
        $this->assertSame(['monthly_expenses'], $comparison['regressions']);
        $this->assertSame([], $comparison['improvements']);
        $failed = collect($candidate)->firstWhere('case_id', 'monthly_expenses');
        $this->assertFalse($failed['grade']['checks']['facts']);
        $this->assertSame(['999'], $failed['grade']['unsupported_numbers']);
    }

    public function test_unexpected_tool_cannot_reach_a_real_handler(): void
    {
        $case = $this->case('monthly_expenses');
        $responses = $case['replay'];
        $responses[0]['output'][0]['name'] = 'execute_sql';
        $result = app(EvaluationRunner::class)->run($case, new FakeAIProvider($responses), $this->route());
        $this->assertFalse($result['grade']['passed']);
        $this->assertFalse($result['grade']['checks']['tool_selection']);
        $this->assertSame(RuntimeException::class, $result['error_type']);
        $this->assertSame(1, $result['provider_calls']);
    }

    public function test_argument_comparison_rejects_foreign_ids_extra_keys_and_wrong_types(): void
    {
        $case = $this->case('monthly_expenses');
        foreach ([['property_id' => 999], ['portfolio_id' => 999], ['property_id' => '101']] as $mutation) {
            $responses = $case['replay'];
            $responses[0]['output'][0]['arguments'] = json_encode([...$case['expected']['calls'][0]['arguments'], ...$mutation]);
            $result = app(EvaluationRunner::class)->run($case, new FakeAIProvider($responses), $this->route());
            $this->assertFalse($result['grade']['checks']['arguments']);
            $this->assertNotNull($result['error_type']);
        }
    }

    public function test_malformed_json_and_incomplete_responses_fail_closed_but_keep_usage(): void
    {
        $case = $this->case('monthly_expenses');
        foreach (['json', 'status'] as $mutation) {
            $responses = $case['replay'];
            if ($mutation === 'json') {
                $responses[0]['output'][0]['arguments'] = '{bad';
            } else {
                $responses[0]['status'] = 'incomplete';
            }
            $result = app(EvaluationRunner::class)->run($case, new FakeAIProvider($responses), $this->route());
            $this->assertFalse($result['grade']['passed']);
            $this->assertGreaterThan(0, $result['usage']['input_tokens']);
        }
    }

    public function test_clarification_is_measured_and_key_order_is_not_significant(): void
    {
        $case = $this->case('ambiguous_property');
        $grade = app(EvaluationGrader::class)->grade($case, $case['expected']['calls'], ['content' => 'No sé.', 'metadata' => ['kind' => 'insufficient_data']], null);
        $this->assertFalse($grade['checks']['clarification']);
        $case = $this->case('monthly_expenses');
        $trace = $case['expected']['calls'];
        $trace[0]['arguments'] = array_reverse($trace[0]['arguments'], true);
        $grade = app(EvaluationGrader::class)->grade($case, $trace, ['content' => 'Los gastos pagados son 84 €.', 'metadata' => ['kind' => 'answer']], null);
        $this->assertTrue($grade['passed']);
    }

    public function test_suite_refuses_nonsynthetic_data_and_comparison_of_different_cases(): void
    {
        $case = $this->case('monthly_expenses');
        $case['synthetic'] = false;
        try {
            app(EvaluationRunner::class)->run($case, new FakeAIProvider, $this->route());
            $this->fail('Expected a synthetic data boundary.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }
        $this->expectException(InvalidArgumentException::class);
        app(EvaluationReport::class)->compare([['case_id' => 'a']], [['case_id' => 'b']]);
    }

    public function test_cli_exports_explicitly_labelled_json_and_fails_for_regression(): void
    {
        Http::preventStrayRequests();
        $exit = Artisan::call('ai:eval', ['--profile' => 'fast', '--json' => true]);
        $report = json_decode(Artisan::output(), true, 64, JSON_THROW_ON_ERROR);
        $this->assertSame(0, $exit);
        $this->assertSame('offline_replay', $report['mode']);
        $this->assertSame('synthetic-read-v1', $report['suite_version']);
        $this->assertSame(64, strlen($report['suite_sha256']));
        $exit = Artisan::call('ai:eval', ['--profile' => 'fast', '--candidate' => 'regression', '--compare' => 'reference', '--json' => true]);
        $report = json_decode(Artisan::output(), true, 64, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $exit);
        $this->assertSame(['monthly_expenses'], $report['comparison']['regressions']);
        Http::assertNothingSent();
    }

    public function test_cli_live_mode_is_blocked_before_any_provider_request(): void
    {
        Http::preventStrayRequests();
        $this->artisan('ai:eval', ['--live' => true])->expectsOutputToContain('No se ha llamado a ningún proveedor')->assertFailed();
        Http::assertNothingSent();
    }

    private function results(string $candidate): array
    {
        $suite = app(FixtureSuite::class);
        $route = $this->route();

        return array_map(fn (array $case) => app(EvaluationRunner::class)->run($case, new FakeAIProvider($suite->responses($case, $candidate, $route['model'])), $route), $suite->cases());
    }

    private function case(string $id): array
    {
        return collect(app(FixtureSuite::class)->cases())->firstWhere('id', $id);
    }

    private function route(): array
    {
        return app(AIModelRouter::class)->route('fast');
    }
}
