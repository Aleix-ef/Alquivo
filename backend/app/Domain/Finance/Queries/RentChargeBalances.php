<?php

namespace App\Domain\Finance\Queries;

use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use Illuminate\Database\Eloquent\Builder;

/** Recorded money only. Cached paid_amount/status are not financial evidence. */
final class RentChargeBalances
{
    public function paid(Portfolio $portfolio): Builder
    {
        return Transaction::query()->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('rent_charge_id', 'rent_charges.id')->whereColumn('lease_id', 'rent_charges.lease_id')
            ->where('property_id', Lease::query()->select('property_id')->whereColumn('id', 'rent_charges.lease_id')->where('portfolio_id', $portfolio->id))
            ->where('portfolio_id', $portfolio->id)->where('direction', 'income')->where('category', 'rent')->where('status', 'paid');
    }

    public function charges(Portfolio $portfolio, ?int $propertyId = null): Builder
    {
        return RentCharge::where('portfolio_id', $portfolio->id)->whereHas('lease', function ($query) use ($portfolio, $propertyId) {
            $query->where('portfolio_id', $portfolio->id)->whereHas('property', fn ($p) => $p->where('portfolio_id', $portfolio->id));
            if ($propertyId !== null) {
                $query->where('property_id', $propertyId);
            }
        });
    }
}
