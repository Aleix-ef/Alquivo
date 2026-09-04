<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\RentCharge;
use App\Domain\Leasing\Models\Lease;
use Carbon\Carbon;

class RentChargeService
{
    public function ensureCurrentCharge(Lease $lease): ?RentCharge
    {
        return $this->ensureChargeForMonth($lease, Carbon::today()->startOfMonth());
    }

    public function ensureChargeForMonth(Lease $lease, Carbon $month): ?RentCharge
    {
        if ($lease->status !== 'active') {
            return null;
        }

        $month = $month->copy()->startOfMonth();
        $start = Carbon::parse($lease->start_date)->startOfMonth();
        $end = $lease->end_date ? Carbon::parse($lease->end_date)->endOfMonth() : null;

        if ($month->lt($start) || ($end && $month->gt($end))) {
            return null;
        }

        $dueDate = $month->copy()->day(min($lease->payment_day, $month->daysInMonth));

        return RentCharge::firstOrCreate(
            ['lease_id' => $lease->id, 'period' => $month->format('Y-m')],
            [
                'portfolio_id' => $lease->portfolio_id,
                'due_date' => $dueDate,
                'amount' => $lease->monthly_rent,
                'paid_amount' => 0,
                'status' => $dueDate->isPast() ? 'overdue' : 'pending',
            ],
        );
    }

    public function refreshOverdueStatuses(): int
    {
        return RentCharge::whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '<', today())
            ->update(['status' => 'overdue']);
    }
}
