<?php

namespace App\Console\Commands;

use App\Domain\Assistant\Evaluation\EvaluationReport;
use App\Domain\Assistant\Evaluation\EvaluationRunner;
use App\Domain\Assistant\Evaluation\FixtureSuite;
use App\Domain\Assistant\Providers\FakeAIProvider;
use App\Domain\Assistant\Services\AIModelRouter;
use Illuminate\Console\Command;
use Throwable;

final class EvaluateAssistant extends Command
{
    protected $signature = 'ai:eval
        {--profile=chat : Perfil configurado, utilizado para modelo y estimación de coste}
        {--candidate=reference : Replay sintético reference o regression}
        {--compare= : Replay de referencia para comparar los mismos casos}
        {--json : Emitir informe JSON sin texto adicional}
        {--live : Reservado; las llamadas reales todavía no están habilitadas}';

    protected $description = 'Evaluar contratos de lectura con fixtures sintéticas, sin red ni acceso a datos de usuarios';

    public function handle(FixtureSuite $suite, EvaluationRunner $runner, EvaluationReport $reports, AIModelRouter $router): int
    {
        if ($this->option('live')) {
            $this->error('Evaluaciones reales aún no habilitadas. No se ha llamado a ningún proveedor. Falta integrar presupuesto y el mismo prompt/orquestador de producción.');

            return self::FAILURE;
        }
        try {
            $route = $router->route((string) $this->option('profile'));
            $cases = $suite->cases();
            $candidate = (string) $this->option('candidate');
            $results = $this->evaluate($suite, $runner, $cases, $route, $candidate);
            $summary = $reports->summarize($results);
            $comparison = null;
            if ($this->option('compare')) {
                $baseline = $this->evaluate($suite, $runner, $cases, $route, (string) $this->option('compare'));
                $comparison = $reports->compare($baseline, $results);
            }
        } catch (Throwable $exception) {
            $this->error('No se pudo ejecutar la suite. Revisa el perfil/candidato y la configuración. Tipo: '.$exception::class);

            return self::FAILURE;
        }
        $report = [
            'mode' => 'offline_replay',
            'suite_version' => FixtureSuite::VERSION,
            'suite_sha256' => hash('sha256', json_encode($cases, JSON_THROW_ON_ERROR)),
            'candidate' => $candidate,
            'profile' => $route['profile'],
            'model' => $route['model'],
            'notice' => 'Respuestas y tokens sintéticos. Coste estimado del replay, sin facturación. Latencia local, no del modelo. No mide calidad real ni prueba aislamiento.',
            'summary' => $summary,
            'comparison' => $comparison,
            'cases' => $results,
        ];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->warn($report['notice']);
            $this->info("Suite {$report['suite_version']}: {$summary['passed']}/{$summary['cases']} casos correctos.");
            $this->table(['Caso', 'Resultado', 'Comprobaciones fallidas'], array_map(fn (array $result) => [
                $result['case_id'],
                $result['grade']['passed'] ? 'OK' : 'FALLO',
                implode(', ', array_keys(array_filter($result['grade']['checks'], fn (bool $passed) => ! $passed))),
            ], $results));
            $this->line('Modelo configurado: '.$route['model'].'; coste sintético estimado (USD): '.($summary['estimated_cost_usd'] ?? 'desconocido'));
            if ($comparison !== null) {
                $this->line('Regresiones: '.(implode(', ', $comparison['regressions']) ?: 'ninguna'));
            }
        }

        return $summary['passed'] === $summary['cases'] ? self::SUCCESS : self::FAILURE;
    }

    private function evaluate(FixtureSuite $suite, EvaluationRunner $runner, array $cases, array $route, string $candidate): array
    {
        return array_map(fn (array $case) => $runner->run(
            $case,
            new FakeAIProvider($suite->responses($case, $candidate, $route['model'])),
            $route,
        ), $cases);
    }
}
