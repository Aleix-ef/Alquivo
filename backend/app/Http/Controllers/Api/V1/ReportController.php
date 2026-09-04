<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Properties\Models\Property;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function overview(Request $request)
    {
        $data = $request->validate(['months' => ['sometimes', 'integer', 'between:3,24']]);
        $months = (int) ($data['months'] ?? 12);
        $portfolio = $request->user()->portfolio();
        $from = now()->startOfMonth()->subMonths($months - 1);
        $transactions = Transaction::where('portfolio_id', $portfolio->id)
            ->where('status', 'paid')->whereDate('transaction_date', '>=', $from)->get();
        $series = collect(range(0, $months - 1))->map(function (int $offset) use ($from, $transactions) {
            $month = $from->copy()->addMonths($offset);
            $items = $transactions->filter(fn (Transaction $transaction) => $transaction->transaction_date->format('Y-m') === $month->format('Y-m'));
            $income = (float) $items->where('direction', 'income')->sum('amount');
            $expenses = (float) $items->where('direction', 'expense')->sum('amount');

            return ['period' => $month->format('Y-m'), 'label' => $month->format('m/Y'), 'income' => $income, 'expenses' => $expenses, 'net' => $income - $expenses];
        });
        $properties = Property::where('portfolio_id', $portfolio->id)->with(['leases' => fn ($query) => $query->where('status', 'active')])->get();
        $performance = $properties->map(function (Property $property) use ($transactions) {
            $items = $transactions->where('property_id', $property->id);
            $income = (float) $items->where('direction', 'income')->sum('amount');
            $expenses = (float) $items->where('direction', 'expense')->sum('amount');
            $annualRent = (float) $property->leases->sum('monthly_rent') * 12;
            $value = (float) $property->current_value;

            return ['id' => $property->id, 'name' => $property->name, 'value' => $value, 'income' => $income, 'expenses' => $expenses, 'net' => $income - $expenses, 'gross_yield' => $value > 0 ? round($annualRent / $value * 100, 2) : null];
        })->sortByDesc('net')->values();

        return [
            'currency' => $portfolio->currency,
            'range' => ['from' => $from->toDateString(), 'to' => today()->toDateString()],
            'totals' => ['income' => $series->sum('income'), 'expenses' => $series->sum('expenses'), 'net' => $series->sum('net')],
            'series' => $series, 'properties' => $performance,
        ];
    }

    public function export(Request $request, string $resource)
    {
        $portfolio = $request->user()->portfolio();
        [$headers, $rows] = match ($resource) {
            'properties' => [
                ['Nombre', 'Tipo', 'Dirección', 'Ciudad', 'Precio compra', 'Valor actual', 'Deuda pendiente'],
                Property::where('portfolio_id', $portfolio->id)->orderBy('name')->get()->map(fn (Property $property) => [$property->name, $property->type, $property->address_line, $property->city, $property->purchase_price, $property->current_value, $property->outstanding_debt]),
            ],
            'leases' => [
                ['Propiedad', 'Inquilinos', 'Estado', 'Inicio', 'Fin', 'Renta mensual', 'Fianza', 'Día cobro'],
                Lease::where('portfolio_id', $portfolio->id)->with(['property', 'participants'])->orderByDesc('start_date')->get()->map(fn (Lease $lease) => [$lease->property->name, $lease->participants->pluck('name')->join(', '), $lease->status, $lease->start_date->toDateString(), $lease->end_date?->toDateString(), $lease->monthly_rent, $lease->deposit_amount, $lease->payment_day]),
            ],
            'transactions' => [
                ['Fecha', 'Tipo', 'Categoría', 'Concepto', 'Propiedad', 'Importe', 'Estado', 'Método'],
                Transaction::where('portfolio_id', $portfolio->id)->with('property')->orderByDesc('transaction_date')->get()->map(fn (Transaction $transaction) => [$transaction->transaction_date->toDateString(), $transaction->direction, $transaction->category, $transaction->description, $transaction->property?->name, $transaction->amount, $transaction->status, $transaction->payment_method]),
            ],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            $rows->each(fn (array $row) => fputcsv($output, array_map($this->sanitizeCsvCell(...), $row)));
            fclose($output);
        }, 'inmogest-'.$resource.'-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function sanitizeCsvCell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
