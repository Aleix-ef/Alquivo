<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Actions\CreateExpense;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Properties\Models\Property;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::where('portfolio_id', $request->user()->portfolio()->id)->with('property');
        if ($request->filled('direction')) {
            $query->where('direction', $request->string('direction'));
        }

        return $query->orderByDesc('transaction_date')->paginate(40);
    }

    public function store(Request $request)
    {
        $portfolio = $request->user()->portfolio();
        $data = $request->validate([
            'property_id' => ['nullable', 'integer'],
            'direction' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', 'string', 'max:50'], 'description' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'gt:0'], 'transaction_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'], 'status' => ['required', Rule::in(['pending', 'paid', 'cancelled'])],
            'payment_method' => ['nullable', 'string', 'max:40'], 'notes' => ['nullable', 'string'],
        ]);
        if ($data['direction'] === 'expense') {
            return response()->json(app(CreateExpense::class)->execute($portfolio, $request->user(), $data), 201);
        }
        if (! empty($data['property_id'])) {
            Property::where('portfolio_id', $portfolio->id)->findOrFail($data['property_id']);
        }

        return response()->json(Transaction::create([...$data, 'portfolio_id' => $portfolio->id]), 201);
    }

    public function update(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->portfolio_id === $request->user()->portfolio()->id, 404);
        abort_if($transaction->rent_charge_id, 422, 'Corrige este cobro desde la operación de alquiler para mantener la mensualidad cuadrada.');
        $data = $request->validate([
            'property_id' => ['sometimes', 'nullable', 'integer'],
            'direction' => ['sometimes', Rule::in(['income', 'expense'])],
            'category' => ['sometimes', 'string', 'max:50'],
            'description' => ['sometimes', 'string', 'max:180'],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'status' => ['sometimes', Rule::in(['pending', 'paid', 'cancelled'])],
            'transaction_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:40'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
        if (array_key_exists('property_id', $data) && $data['property_id']) {
            Property::where('portfolio_id', $request->user()->portfolio()->id)->findOrFail($data['property_id']);
        }
        if ($transaction->recurring_rule_id) {
            $data = array_intersect_key($data, array_flip(['status', 'transaction_date', 'payment_method', 'notes']));
        }
        $transaction->update($data);

        return $transaction->fresh('property');
    }
}
