<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attention\Services\PortfolioAttention;
use App\Domain\Finance\Models\Transaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $portfolio = $request->user()->portfolio();
        $properties = $portfolio->properties()->with(['photos', 'leases' => fn ($q) => $q->where('status', 'active')])->get();
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $transactions = Transaction::where('portfolio_id', $portfolio->id)->whereBetween('transaction_date', [$from, $to])->get();
        $income = (float) $transactions->where('direction', 'income')->where('status', 'paid')->sum('amount');
        $expenses = (float) $transactions->where('direction', 'expense')->where('status', 'paid')->sum('amount');
        $value = (float) $properties->sum('current_value');
        $debt = (float) $properties->sum('outstanding_debt');
        $monthlyRent = (float) $properties->flatMap->leases->sum('monthly_rent');
        $attention = app(PortfolioAttention::class)->snapshot($portfolio);

        return [
            'period' => now()->format('Y-m'),
            'metrics' => [
                'portfolio_value' => $value, 'net_equity' => $value - $debt,
                'monthly_income' => $income, 'monthly_expenses' => $expenses,
                'net_profit' => $income - $expenses, 'contracted_rent' => $monthlyRent,
                'gross_yield' => $value > 0 ? round(($monthlyRent * 12 / $value) * 100, 2) : null,
                'occupancy_rate' => $properties->count() ? round(($properties->filter(fn ($p) => $p->leases->isNotEmpty())->count() / $properties->count()) * 100, 1) : null,
            ],
            'properties' => $properties->take(6)->values(),
            'attention' => $attention['items'],
            'attention_summary' => array_diff_key($attention, ['items' => true]),
        ];
    }
}
