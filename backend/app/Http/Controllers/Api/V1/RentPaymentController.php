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
        $data = $this->validatePayment($request);

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
                'notes' => $data['notes'] ?? null,
            ]);
            $this->syncCharge($charge);

            return $transaction;
        });

        return response()->json($transaction, 201);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $this->ensurePaymentOwned($request, $transaction);
        $data = $this->validatePayment($request);

        $updated = DB::transaction(function () use ($transaction, $data) {
            $payment = Transaction::lockForUpdate()->findOrFail($transaction->id);
            $charge = RentCharge::lockForUpdate()->findOrFail($payment->rent_charge_id);
            $paidByOthers = (float) $charge->transactions()->where('status', 'paid')->whereKeyNot($payment->id)->sum('amount');
            if ($paidByOthers + (float) $data['amount'] > (float) $charge->amount) {
                throw ValidationException::withMessages(['amount' => ['El total de cobros supera la mensualidad.']]);
            }
            $payment->update([
                'amount' => $data['amount'],
                'transaction_date' => $data['transaction_date'],
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->syncCharge($charge);

            return $payment->fresh('property');
        });

        return $updated;
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        $this->ensurePaymentOwned($request, $transaction);
        DB::transaction(function () use ($transaction) {
            $payment = Transaction::lockForUpdate()->findOrFail($transaction->id);
            $charge = RentCharge::lockForUpdate()->findOrFail($payment->rent_charge_id);
            $payment->delete();
            $this->syncCharge($charge);
        });

        return response()->noContent();
    }

    private function validatePayment(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'transaction_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function ensurePaymentOwned(Request $request, Transaction $transaction): void
    {
        abort_unless($transaction->portfolio_id === $request->user()->portfolio()->id, 404);
        abort_unless($transaction->rent_charge_id, 422, 'Este movimiento no es un cobro de alquiler.');
    }

    private function syncCharge(RentCharge $charge): void
    {
        $paid = (float) $charge->transactions()->where('status', 'paid')->sum('amount');
        $status = $paid >= (float) $charge->amount
            ? 'paid'
            : ($paid > 0 ? 'partial' : ($charge->due_date->isPast() ? 'overdue' : 'pending'));
        $charge->update(['paid_amount' => $paid, 'status' => $status]);
    }
}
