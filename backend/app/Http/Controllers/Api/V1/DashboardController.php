<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attention\Models\Issue;
use App\Domain\Attention\Models\Reminder;
use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Lease;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $portfolio = $request->user()->portfolio();
        $properties = $portfolio->properties()->with(['leases' => fn ($q) => $q->where('status', 'active')])->get();
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $transactions = Transaction::where('portfolio_id', $portfolio->id)->whereBetween('transaction_date', [$from, $to])->get();
        $income = (float) $transactions->where('direction', 'income')->where('status', 'paid')->sum('amount');
        $expenses = (float) $transactions->where('direction', 'expense')->where('status', 'paid')->sum('amount');
        $value = (float) $properties->sum('current_value');
        $debt = (float) $properties->sum('outstanding_debt');
        $monthlyRent = (float) $properties->flatMap->leases->sum('monthly_rent');
        $charges = RentCharge::where('portfolio_id', $portfolio->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->with('lease.property')->orderBy('due_date')->get();
        $expiring = Lease::where('portfolio_id', $portfolio->id)->where('status', 'active')
            ->whereBetween('end_date', [today(), today()->addDays(60)])
            ->with('property')->orderBy('end_date')->get();
        $issues = Issue::where('portfolio_id', $portfolio->id)
            ->whereNotIn('status', ['resolved', 'cancelled'])
            ->where(fn ($query) => $query->where('priority', 'high')->orWhereDate('due_date', '<=', today()->addDays(14)))
            ->with('property')->orderBy('due_date')->get();
        $documents = Document::where('portfolio_id', $portfolio->id)
            ->whereBetween('expires_at', [today(), today()->addDays(30)])
            ->with('property')->orderBy('expires_at')->get();
        $reminders = Reminder::where('portfolio_id', $portfolio->id)->whereNull('completed_at')
            ->whereBetween('starts_at', [now(), now()->addDays(14)])
            ->with('property')->orderBy('starts_at')->get();
        $attention = $charges->map(fn (RentCharge $charge) => [
            'type' => $charge->due_date->isPast() ? 'rent_overdue' : 'rent_pending',
            'title' => $charge->due_date->isPast() ? 'Alquiler atrasado' : 'Alquiler pendiente',
            'detail' => $charge->lease->property->name,
            'amount' => (float) $charge->amount - (float) $charge->paid_amount,
            'date' => $charge->due_date->toDateString(),
        ])->concat($expiring->map(fn (Lease $lease) => [
            'type' => 'lease_expiring', 'title' => 'Contrato próximo a vencer',
            'detail' => $lease->property->name, 'amount' => null, 'date' => $lease->end_date->toDateString(),
        ]))->concat($issues->map(fn (Issue $issue) => [
            'type' => 'issue', 'title' => 'Incidencia pendiente',
            'detail' => $issue->property->name.' · '.$issue->title,
            'amount' => $issue->estimated_cost ? (float) $issue->estimated_cost : null,
            'date' => $issue->due_date?->toDateString() ?? $issue->reported_at->toDateString(),
        ]))->concat($documents->map(fn (Document $document) => [
            'type' => 'document_expiry', 'title' => 'Documento próximo a vencer',
            'detail' => $document->name.($document->property ? ' · '.$document->property->name : ''),
            'amount' => null, 'date' => $document->expires_at->toDateString(),
        ]))->concat($reminders->map(fn (Reminder $reminder) => [
            'type' => 'reminder', 'title' => $reminder->title,
            'detail' => $reminder->property?->name ?? 'Cartera general',
            'amount' => null, 'date' => $reminder->starts_at->toDateString(),
        ]))->sortBy('date')->values();

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
            'attention' => $attention,
        ];
    }
}
