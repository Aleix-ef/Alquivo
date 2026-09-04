<?php

namespace App\Console\Commands;

use App\Domain\Finance\Services\RentChargeService;
use App\Domain\Leasing\Models\Lease;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRentCharges extends Command
{
    protected $signature = 'rent:generate {--month= : Mes en formato YYYY-MM}';

    protected $description = 'Genera de forma idempotente los cargos mensuales de alquiler';

    public function handle(RentChargeService $charges): int
    {
        $month = $this->option('month') ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth() : today()->startOfMonth();
        $created = 0;

        Lease::where('status', 'active')->each(function (Lease $lease) use ($charges, $month, &$created) {
            $before = $lease->charges()->where('period', $month->format('Y-m'))->exists();
            $charge = $charges->ensureChargeForMonth($lease, $month);
            if ($charge && ! $before) {
                $created++;
            }
        });
        $overdue = $charges->refreshOverdueStatuses();
        $this->info("Cargos creados: {$created}. Actualizados como atrasados: {$overdue}.");

        return self::SUCCESS;
    }
}
