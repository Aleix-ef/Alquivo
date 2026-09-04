<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Models\Transaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentPaymentController extends Controller
{
    public function store(Request $request, RentCharge $rentCharge)
    {
        $portfolio = $request->user()->portfolio();
        abort_unless($rentCharge->portfolio_id === $portfolio->id, 404);
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'transaction_date' => ['required', 'date'], 'payment_method' => ['nullable', 'string', 'max:40']]);

        $transaction = DB::transaction(function () use ($rentCharge, $data) {
            $charge = RentCharge::lockForUpdate()->findOrFail($rentCharge->id);
            $remaining = (float) $charge->amount - (float) $charge->paid_amount;
            if ((float) $data['amount'] > $remaining) {
                throw ValidationException::withMessages(['amount' => ['El importe supera la cantidad pendiente.']]);
            }
            $transaction = Transaction::create([
                'portfolio_id' => $charge->portfolio_id, 'property_id' => $charge->lease->property_id,
                'lease_id' => $charge->lease_id, 'rent_charge_id' => $charge->id,
                'direction' => 'income', 'category' => 'rent', 'description' => 'Alquiler '.$charge->period,
                'amount' => $data['amount'], 'transaction_date' => $data['transaction_date'],
                'status' => 'paid', 'payment_method' => $data['payment_method'] ?? null,
            ]);
            $paid = (float) $charge->paid_amount + (float) $data['amount'];
            $charge->update(['paid_amount' => $paid, 'status' => $paid >= (float) $charge->amount ? 'paid' : 'partial']);

            return $transaction;
        });

        return response()->json($transaction, 201);
    }
}
