<?php

namespace App\Console\Commands;

use App\Domain\Assistant\Models\AiRunStep;
use Illuminate\Console\Command;

/** Read-only, bounded operational report. Never reads chats or calls the provider. */
class AssistantLatency extends Command
{
    protected $signature = 'assistant:latency {--days=7} {--limit=1000} {--json}';

    protected $description = 'Resumir tiempos de llamadas y herramientas sin mostrar conversaciones ni datos de carteras';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 30]]);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]);
        if ($days === false || $limit === false) {
            $this->error('days debe estar entre 1 y 30; limit entre 1 y 5000.');

            return self::FAILURE;
        }
        $query = AiRunStep::whereIn('kind', ['provider', 'tool'])->where('created_at', '>=', now()->subDays($days));
        $count = (clone $query)->count();
        $steps = $query->select(['kind', 'status', 'model', 'tool', 'latency_ms', 'input_tokens', 'cached_input_tokens'])
            ->orderByDesc('id')->limit($limit)->get();
        $groups = $steps->groupBy(function ($step) {
            $name = $step->kind === 'provider' ? $step->model : $step->tool;
            $name = is_string($name) && preg_match('/\A[a-zA-Z0-9_.-]{1,120}\z/', $name) ? $name : 'unregistered';

            return $step->kind.':'.$name;
        })->map(function ($items, $key) {
            [$kind, $name] = explode(':', $key, 2);
            $times = $items->whereNotNull('latency_ms')->pluck('latency_ms')->map(fn ($ms) => (int) $ms)->sort()->values()->all();
            $n = count($times);
            $median = $n ? ($times[(int) floor(($n - 1) / 2)] + $times[(int) floor($n / 2)]) / 2 : null;
            $input = $items->sum('input_tokens');

            return ['kind' => $kind, 'name' => $name, 'count' => $items->count(),
                'failed_or_rejected' => $items->whereIn('status', ['failed', 'rejected'])->count(),
                'unknown_latency' => $items->count() - $n, 'median_ms' => $median,
                'p95_ms' => $n ? $times[(int) ceil($n * 0.95) - 1] : null, 'max_ms' => $n ? max($times) : null,
                'cached_input_percent' => $kind === 'provider' && $input > 0 ? round($items->sum('cached_input_tokens') / $input * 100, 2) : null];
        })->sortBy(fn ($g) => $g['kind'].':'.$g['name'])->values()->all();
        $report = ['days' => $days, 'matched_steps' => $count, 'sampled_steps' => $steps->count(),
            'partial' => $count > $steps->count(), 'groups' => $groups];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }
        $this->line('Tiempos por paso, no tiempo completo del chat. Sin inferencias ni contenido privado.');
        $this->line('Muestra: '.$steps->count().' de '.$count.' pasos de los últimos '.$days.' días.');
        if ($report['partial']) {
            $this->warn('Muestra parcial de los pasos más recientes; no representa todos los resultados.');
        }
        $this->table(['Tipo', 'Modelo / tool', 'Pasos', 'Fallidos', 'Sin tiempo', 'Mediana ms', 'p95 ms', 'Máx ms', 'Entrada cache %'],
            array_map(fn ($group) => array_map(fn ($value) => $value ?? '—', array_values($group)), $groups));

        return self::SUCCESS;
    }
}
