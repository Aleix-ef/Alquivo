<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PropertyAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** All rent payment writes serialize on the portfolio and existing monthly charge. */
final class RecordRentPayment
{
    public function __construct(private readonly PropertyAccess $properties) {}

    public function validatedData(Portfolio $portfolio, User $user, array $input): array
    {
        $this->authorize($portfolio, $user);
        $data = $this->validatedFields($input);
        $charge = $this->charge($portfolio, $data['rent_charge_id']);
        $this->assertAcceptsNewPayment($charge);
        $this->assertWithinBalance($charge, $data['amount']);

        return $data;
    }

    public function execute(Portfolio $portfolio, User $user, array $input): Transaction
    {
        return DB::transaction(function () use ($portfolio, $user, $input) {
            $portfolio = Portfolio::query()->lockForUpdate()->findOrFail($portfolio->id);
            $this->authorize($portfolio, $user);
            $data = $this->validatedFields($input);
            $charge = $this->charge($portfolio, $data['rent_charge_id'], true);
            $this->assertAcceptsNewPayment($charge);
            $this->assertWithinBalance($charge, $data['amount']);
            $payment = Transaction::create([
                'portfolio_id' => $portfolio->id, 'property_id' => $charge->lease->property_id,
                'lease_id' => $charge->lease_id, 'rent_charge_id' => $charge->id,
                'direction' => 'income', 'category' => 'rent', 'description' => 'Alquiler '.$charge->period,
                'amount' => $data['amount'], 'transaction_date' => $data['transaction_date'],
                'status' => 'paid', 'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->syncCharge($charge);

            return $payment;
        });
    }

    public function update(Portfolio $portfolio, User $user, Transaction $payment, array $input): Transaction
    {
        return DB::transaction(function () use ($portfolio, $user, $payment, $input) {
            $portfolio = Portfolio::query()->lockForUpdate()->findOrFail($portfolio->id);
            $this->authorize($portfolio, $user);
            $this->assertPayment($portfolio, $payment);
            // Lock order is portfolio → charge → lease → property → payment for all writers.
            $charge = $this->charge($portfolio, $payment->rent_charge_id, true);
            $payment = Transaction::query()->lockForUpdate()->findOrFail($payment->id);
            $this->assertPayment($portfolio, $payment, $charge);
            $data = $this->validatedFields([...$input, 'rent_charge_id' => $charge->id]);
            $this->assertWithinBalance($charge, $data['amount'], $payment->id);
            $payment->update([
                'amount' => $data['amount'], 'transaction_date' => $data['transaction_date'],
                'payment_method' => $data['payment_method'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
            $this->syncCharge($charge);

            return $payment->fresh('property');
        });
    }

    public function delete(Portfolio $portfolio, User $user, Transaction $payment): void
    {
        DB::transaction(function () use ($portfolio, $user, $payment) {
            $portfolio = Portfolio::query()->lockForUpdate()->findOrFail($portfolio->id);
            $this->authorize($portfolio, $user);
            $this->assertPayment($portfolio, $payment);
            $charge = $this->charge($portfolio, $payment->rent_charge_id, true);
            $payment = Transaction::query()->lockForUpdate()->findOrFail($payment->id);
            $this->assertPayment($portfolio, $payment, $charge);
            $payment->delete();
            $this->syncCharge($charge);
        });
    }

    private function validatedFields(array $input): array
    {
        $data = Validator::make($input, [
            'rent_charge_id' => ['required', 'integer', 'min:1'],
            // REST numeric input remains compatible; all calculations use decimal strings.
            'amount' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();
        [$whole, $fraction] = array_pad(explode('.', (string) $data['amount']), 2, '');
        $data['amount'] = ((string) (int) $whole).'.'.str_pad($fraction, 2, '0');
        if (bccomp($data['amount'], '0.00', 2) <= 0) {
            throw ValidationException::withMessages(['amount' => ['El importe debe ser mayor que cero.']]);
        }
        $data['rent_charge_id'] = (int) $data['rent_charge_id'];

        return $data;
    }

    private function charge(Portfolio $portfolio, int $chargeId, bool $lock = false): RentCharge
    {
        $query = RentCharge::query()->where('portfolio_id', $portfolio->id);
        $charge = ($lock ? $query->lockForUpdate() : $query)->findOrFail($chargeId);
        $query = Lease::query()->where('portfolio_id', $portfolio->id);
        $lease = ($lock ? $query->lockForUpdate() : $query)->findOrFail($charge->lease_id);
        $this->properties->assertWritable($portfolio, $lease->property_id);
        if ($lock) {
            $portfolio->properties()->lockForUpdate()->findOrFail($lease->property_id);
        }
        $charge->setRelation('lease', $lease);

        return $charge;
    }

    /** Existing receipts remain correctable even after cancelling the contract or charge. */
    private function assertAcceptsNewPayment(RentCharge $charge): void
    {
        if (! in_array($charge->lease->status, ['active', 'ended'], true) || $charge->status === 'cancelled') {
            throw ValidationException::withMessages(['rent_charge_id' => ['Esta mensualidad no admite cobros. Revisa el contrato.']]);
        }
    }

    private function paidAmount(RentCharge $charge, ?int $exceptPayment = null): string
    {
        $query = $charge->transactions()->where('portfolio_id', $charge->portfolio_id)
            ->where('lease_id', $charge->lease_id)->where('property_id', $charge->lease->property_id)
            ->where('direction', 'income')->where('category', 'rent')->where('status', 'paid');
        if ($exceptPayment !== null) {
            $query->whereKeyNot($exceptPayment);
        }

        return $query->pluck('amount')->reduce(fn (string $sum, $amount) => bcadd($sum, (string) $amount, 2), '0.00');
    }

    private function assertWithinBalance(RentCharge $charge, string $amount, ?int $exceptPayment = null): void
    {
        $remaining = bcsub($charge->amount, $this->paidAmount($charge, $exceptPayment), 2);
        if (bccomp($amount, $remaining, 2) > 0) {
            throw ValidationException::withMessages(['amount' => ['El importe supera la cantidad pendiente.']]);
        }
    }

    private function syncCharge(RentCharge $charge): void
    {
        $paid = $this->paidAmount($charge);
        // Correcting a historical receipt must not reactivate an explicitly cancelled charge.
        $status = $charge->status === 'cancelled' ? 'cancelled' : (
            bccomp($paid, $charge->amount, 2) >= 0
                ? 'paid'
                : (bccomp($paid, '0.00', 2) > 0 ? 'partial' : ($charge->due_date->lt(today()) ? 'overdue' : 'pending'))
        );
        $charge->update(['paid_amount' => $paid, 'status' => $status]);
    }

    private function assertPayment(Portfolio $portfolio, Transaction $payment, ?RentCharge $charge = null): void
    {
        abort_unless($payment->portfolio_id === $portfolio->id, 404);
        abort_unless($payment->rent_charge_id && $payment->direction === 'income' && $payment->category === 'rent' && $payment->status === 'paid', 422, 'Este movimiento no es un cobro de alquiler.');
        if ($charge) {
            abort_unless($payment->rent_charge_id === $charge->id && $payment->lease_id === $charge->lease_id && $payment->property_id === $charge->lease->property_id, 404);
        }
    }

    private function authorize(Portfolio $portfolio, User $user): void
    {
        abort_unless($portfolio->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists(), 404);
    }
}
