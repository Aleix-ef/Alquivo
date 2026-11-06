<?php

namespace App\Domain\Administration\Services;

use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PlanService;
use App\Domain\Portfolio\Services\StorageUsageService;
use App\Support\ProductFeatures;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Read-only, aggregate-only business queries. Never loads contacts, files or AI message bodies. */
final class BusinessMetrics
{
    public function users(AdminRange $range, int $actor): Builder
    {
        return DB::table('users')->when(! $range->includeSelf, fn (Builder $q) => $q->where('users.id', '<>', $actor));
    }

    private function portfolios(AdminRange $range, int $actor): Builder
    {
        return DB::table('portfolios')->whereExists(function (Builder $q) use ($range, $actor) {
            $q->selectRaw('1')->from('portfolio_members')->whereColumn('portfolio_members.portfolio_id', 'portfolios.id')
                ->where('portfolio_members.role', 'owner')->whereIn('portfolio_members.user_id', $this->users($range, $actor)->select('users.id'));
        });
    }

    public function inRange(Builder $query, AdminRange $range, string $column): Builder
    {
        return $query->where($column, '>=', $range->from->utc())->where($column, '<', $range->until->utc());
    }

    public function runs(AdminRange $range, int $actor): Builder
    {
        return $this->inRange(DB::table('ai_runs')->whereIn('user_id', $this->users($range, $actor)->select('users.id')), $range, 'ai_runs.created_at');
    }

    public function overview(AdminRange $range, int $actor): array
    {
        $users = $this->users($range, $actor);
        $portfolios = $this->portfolios($range, $actor);
        $ids = (clone $portfolios)->select('portfolios.id');
        $properties = DB::table('properties')->whereNull('deleted_at')->whereIn('portfolio_id', clone $ids);
        $leases = DB::table('leases')->whereNull('deleted_at')->whereIn('portfolio_id', clone $ids);
        $documents = DB::table('documents')->whereIn('portfolio_id', clone $ids);
        $distribution = [];
        $plans = app(PlanService::class);
        // Streaming only plan metadata; no per-account storage/count queries.
        foreach ((clone $portfolios)->select('portfolios.id', 'plan', 'trial_ends_at')->orderBy('portfolios.id')->cursor() as $row) {
            $portfolio = (new Portfolio)->newFromBuilder((array) $row);
            $code = $plans->effectiveCode($portfolio);
            $distribution[$code] = ($distribution[$code] ?? 0) + 1;
        }
        $catalog = $plans->catalog();
        $registered = $this->inRange(clone $users, $range, 'users.created_at');
        $series = $this->registrationSeries(clone $registered, $range);
        $count = (clone $users)->count();

        return [
            'range' => $range->payload(), 'generated_at' => now()->toIso8601String(),
            'population_note' => $range->includeSelf ? 'Incluye la cuenta administradora. Las cuentas de prueba no se identifican automáticamente.' : 'Excluye tu cuenta administradora. Las demás cuentas de prueba no se identifican automáticamente.',
            'users' => ['total_existing' => $count, 'registered_in_range' => (clone $registered)->count(),
                'verified_existing' => (clone $users)->whereNotNull('email_verified_at')->count(), 'timeline' => $series,
                'active_users' => null, 'deleted_users' => null,
                'note' => 'Registros de cuentas que siguen existiendo. No reconstruye cuentas eliminadas, actividad ni bajas históricas.'],
            'plans' => ['distribution' => collect($distribution)->map(fn (int $number, string $code) => [
                'code' => $code, 'name' => $code === 'admin' ? 'Pruebas locales' : ($catalog[$code]['name'] ?? $code), 'portfolios' => $number,
            ])->values()->all(), 'billing_enabled' => app(ProductFeatures::class)->billing(),
                'conversions' => null, 'changes' => null,
                'note' => 'Distribución actual de carteras por acceso efectivo de PlanService, no por precio ni facturación. No existe un historial completo de cambios.'],
            'activation' => ['eligible_users' => $count,
                'with_property' => $this->ownersWith($users, 'properties', fn (Builder $q) => $q->whereNull('properties.deleted_at')),
                'with_active_lease' => $this->ownersWith($users, 'leases', fn (Builder $q) => $q->whereNull('leases.deleted_at')->where('leases.status', 'active')),
                'with_recorded_payment' => $this->ownersWith($users, 'transactions', fn (Builder $q) => $q->where('transactions.direction', 'income')->where('transactions.category', 'rent')->where('transactions.status', 'paid')->whereNotNull('transactions.rent_charge_id')),
                'with_expense' => $this->ownersWith($users, 'transactions', fn (Builder $q) => $q->where('transactions.direction', 'expense')->where('transactions.status', 'paid')),
                'note' => 'Fotografía actual de propietarios con datos conservados, no un embudo histórico ni una prueba de cobro bancario.'],
            'usage' => ['portfolios' => (clone $portfolios)->count(), 'properties' => (clone $properties)->count(),
                'active_leases' => (clone $leases)->where('status', 'active')->count(), 'documents' => (clone $documents)->count(),
                'storage_bytes' => app(StorageUsageService::class)->totalForPortfolios($portfolios), 'rental_units' => null,
                'note' => 'Datos actuales. Almacenamiento de cuota: documentos y fotos, no disco total del VPS. No existe aún un modelo de unidades alquilables.'],
            'ai' => $this->ai($range, $actor),
            'revenue' => ['status' => 'unavailable', 'mrr' => null, 'collected' => null, 'cancellations' => null,
                'note' => 'Sin libro conciliado de facturas, cobros y devoluciones de Stripe. No se usan rentas de los propietarios ni precios del catálogo como ingresos de Alquivo.'],
            'retention' => ['status' => 'unavailable', 'note' => 'No hay un historial fiable de actividad útil por día. No se usa el login ni updated_at como sustituto de retención.'],
            'acquisition' => ['status' => 'unavailable', 'note' => 'No se registra atribución de origen/campaña. No se han añadido cookies de seguimiento ni analítica externa.'],
        ];
    }

    private function ownersWith(Builder $users, string $table, \Closure $filter): int
    {
        return (clone $users)->whereExists(function (Builder $q) use ($table, $filter) {
            $q->selectRaw('1')->from('portfolio_members')->join($table, "$table.portfolio_id", '=', 'portfolio_members.portfolio_id')
                ->whereColumn('portfolio_members.user_id', 'users.id')->where('portfolio_members.role', 'owner');
            $filter($q);
        })->count();
    }

    private function registrationSeries(Builder $registered, AdminRange $range): array
    {
        // Hourly SQL aggregation stays bounded; PHP only converts buckets across DST.
        $hour = DB::getDriverName() === 'pgsql' ? "date_trunc('hour', users.created_at)" : "strftime('%Y-%m-%d %H:00:00', users.created_at)";
        $daily = [];
        foreach ($registered->selectRaw("$hour as hour, COUNT(*) as total")->groupBy('hour')->get() as $row) {
            $day = CarbonImmutable::parse($row->hour, 'UTC')->setTimezone(config('admin.timezone'))->toDateString();
            $daily[$day] = ($daily[$day] ?? 0) + (int) $row->total;
        }
        $series = [];
        for ($day = $range->from; $day < $range->until; $day = $day->addDay()) {
            $series[] = ['date' => $day->toDateString(), 'registrations' => $daily[$day->toDateString()] ?? 0];
        }

        return $series;
    }

    private function ai(AdminRange $range, int $actor): array
    {
        $runs = $this->runs($range, $actor);
        $totals = (clone $runs)->selectRaw('COUNT(*) as runs, COUNT(DISTINCT user_id) as users, COALESCE(SUM(input_tokens), 0) as input_tokens, COALESCE(SUM(output_tokens), 0) as output_tokens, COALESCE(SUM(estimated_cost_nano_usd), 0) as known_cost, COALESCE(SUM(reserved_cost_nano_usd), 0) as reserved')->first();
        $steps = DB::table('ai_run_steps')->where('kind', 'provider')->whereIn('run_id', (clone $runs)->select('ai_runs.id'));
        $models = (clone $steps)->select('model')->selectRaw('COUNT(*) as calls, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens, SUM(cached_input_tokens) as cached_input_tokens, COALESCE(SUM(estimated_cost_nano_usd), 0) as known_cost, SUM(CASE WHEN estimated_cost_nano_usd IS NULL THEN 1 ELSE 0 END) as unknown_cost_calls, AVG(latency_ms) as latency_ms')->groupBy('model')->orderBy('model')->get();
        $cutoff = CarbonImmutable::now()->startOfMonth()->subMonths((int) config('ai.limits.metrics_retention_months', 12));

        return ['runs' => (int) $totals->runs, 'users' => (int) $totals->users,
            'completed' => (clone $runs)->where('status', 'completed')->count(),
            'failed' => (clone $runs)->where('status', 'failed')->count(),
            'processing' => (clone $runs)->where('status', 'processing')->count(),
            'input_tokens' => (int) $totals->input_tokens, 'output_tokens' => (int) $totals->output_tokens,
            'known_cost_nano_usd' => (string) $totals->known_cost, 'reserved_cost_nano_usd' => (string) $totals->reserved,
            'incomplete_cost_runs' => (clone $runs)->where('cost_incomplete', true)->count(),
            'provider_calls' => (clone $steps)->count(),
            'failed_provider_calls' => (clone $steps)->where('status', 'failed')->count(),
            'by_model' => $models->map(fn ($row) => ['model' => $row->model ?? 'No registrado', 'calls' => (int) $row->calls,
                'input_tokens' => (int) $row->input_tokens, 'output_tokens' => (int) $row->output_tokens,
                'cached_input_tokens' => (int) $row->cached_input_tokens, 'known_cost_nano_usd' => (string) $row->known_cost,
                'unknown_cost_calls' => (int) $row->unknown_cost_calls, 'latency_ms' => $row->latency_ms === null ? null : (int) round($row->latency_ms)])->all(),
            'coverage' => ['retention_months' => (int) config('ai.limits.metrics_retention_months', 12),
                'requested_before_retention' => $range->from->utc() < $cutoff,
                'earliest_retained_run' => $this->usersRunStart($range, $actor)],
            'note' => 'Ejecuciones conservadas, no contenido de conversaciones. Coste estimado en USD; reservas e importes incompletos no son gasto confirmado. Una consulta puede usar varias llamadas o ninguna. Las cuentas eliminadas y la caducidad reducen el histórico.'];
    }

    private function usersRunStart(AdminRange $range, int $actor): ?string
    {
        $date = DB::table('ai_runs')->whereIn('user_id', $this->users($range, $actor)->select('users.id'))->min('created_at');

        return $date ? CarbonImmutable::parse($date, 'UTC')->toIso8601String() : null;
    }

    public function userPage(AdminRange $range, int $actor, ?string $search): array
    {
        $query = $this->inRange($this->users($range, $actor), $range, 'users.created_at');
        if ($search !== null && $search !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
            $query->where(fn (Builder $q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like])->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$like]));
        }
        $page = $query->select('users.id', 'name', 'email', 'email_verified_at', 'users.created_at')->orderByDesc('users.created_at')->orderByDesc('users.id')->paginate(25);

        return ['data' => $page->getCollection()->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'email' => $row->email,
            'verified' => $row->email_verified_at !== null, 'registered_at' => CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601String()])->all(),
            'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()];
    }

    public function aiUserPage(AdminRange $range, int $actor): array
    {
        $runs = $this->runs($range, $actor);
        $page = DB::table('users')->joinSub($runs->select('user_id')->selectRaw('COUNT(*) as runs, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens, SUM(estimated_cost_nano_usd) as known_cost, SUM(CASE WHEN cost_incomplete THEN 1 ELSE 0 END) as incomplete_cost_runs')->groupBy('user_id'), 'usage', 'usage.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', 'usage.*')->orderByDesc('usage.known_cost')->orderBy('users.id')->paginate(25);

        return ['data' => $page->getCollection()->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'email' => $row->email,
            'runs' => (int) $row->runs, 'input_tokens' => (int) $row->input_tokens, 'output_tokens' => (int) $row->output_tokens,
            'known_cost_nano_usd' => (string) $row->known_cost, 'incomplete_cost_runs' => (int) $row->incomplete_cost_runs])->all(),
            'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()];
    }
}
