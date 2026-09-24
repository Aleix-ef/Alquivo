<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Actions\RecordRentPayment;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Models\Transaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RentPaymentController extends Controller
{
    public function store(Request $request, RentCharge $rentCharge, RecordRentPayment $payments)
    {
        $transaction = $payments->execute($request->user()->portfolio(), $request->user(), [
            ...$request->only(['amount', 'transaction_date', 'payment_method', 'notes']),
            'rent_charge_id' => $rentCharge->id,
        ]);

        return response()->json($transaction, 201);
    }

    public function update(Request $request, Transaction $transaction, RecordRentPayment $payments)
    {
        return $payments->update($request->user()->portfolio(), $request->user(), $transaction,
            $request->only(['amount', 'transaction_date', 'payment_method', 'notes']));
    }

    public function destroy(Request $request, Transaction $transaction, RecordRentPayment $payments)
    {
        $payments->delete($request->user()->portfolio(), $request->user(), $transaction);

        return response()->noContent();
    }
}
