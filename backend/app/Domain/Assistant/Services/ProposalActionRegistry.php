<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Finance\Actions\CreateExpense;
use App\Domain\Finance\Actions\RecordRentPayment;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Leasing\Actions\UpdateContactPhone;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Properties\Actions\AppendPropertyNote;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Type-specific contracts; lifecycle, authorization, receipts and locks remain in ActionProposalService. */
final class ProposalActionRegistry
{
    private const FIELDS = [
        'expense' => ['property_id', 'amount', 'category', 'description', 'transaction_date', 'status'],
        'contact_phone' => ['contact_id', 'phone'],
        'rent_payment' => ['rent_charge_id', 'amount', 'transaction_date', 'payment_method'],
        'property_note' => ['property_id', 'note'],
    ];

    public function __construct(
        private readonly CreateExpense $expense,
        private readonly UpdateContactPhone $phone,
        private readonly RecordRentPayment $payment,
        private readonly AppendPropertyNote $note,
    ) {}

    public function supports(string $type): bool
    {
        return isset(self::FIELDS[$type]);
    }

    public function editableFields(string $type): array
    {
        $this->assertSupported($type);

        return array_values(array_diff(self::FIELDS[$type], ['property_id', 'contact_id', 'rent_charge_id']));
    }

    public function assertFields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['proposal' => ['La propuesta contiene campos que no están permitidos.']]);
        }
    }

    public function validatedData(string $type, Portfolio $portfolio, User $user, array $input): array
    {
        $this->assertSupported($type);
        $this->assertFields($input, self::FIELDS[$type]);
        $rules = match ($type) {
            'expense' => [
                'property_id' => ['required', 'integer', 'min:1'],
                'amount' => ['required', 'string'],
                'category' => ['required', Rule::in(['maintenance', 'tax', 'insurance', 'other'])],
                'description' => ['required', 'string', 'max:180'],
                'transaction_date' => ['required', 'date_format:Y-m-d'],
                'status' => ['required', Rule::in(['paid', 'pending'])],
            ],
            'contact_phone' => [
                'contact_id' => ['required', 'integer', 'min:1'],
                // Deletion is deliberately not an AI action, even if manual forms allow it.
                'phone' => ['required', 'string', 'max:30'],
            ],
            'rent_payment' => [
                'rent_charge_id' => ['required', 'integer', 'min:1'],
                'amount' => ['required', 'string'],
                'transaction_date' => ['required', 'date_format:Y-m-d'],
                'payment_method' => ['nullable', 'string', 'max:40'],
            ],
            'property_note' => [
                'property_id' => ['required', 'integer', 'min:1'],
                'note' => ['required', 'string', 'max:2000'],
            ],
        };
        $input = Validator::make($input, $rules)->validate();

        return match ($type) {
            'expense' => $this->expense->validatedData($portfolio, $user, $input),
            'contact_phone' => $this->phone->validatedData($portfolio, $user, $input),
            'rent_payment' => $this->payment->validatedData($portfolio, $user, $input),
            'property_note' => $this->note->validatedData($portfolio, $user, $input),
        };
    }

    /** Called only while holding actor, portfolio and proposal/run locks. No model text is executable. */
    public function snapshot(string $type, Portfolio $portfolio, array $payload): array
    {
        $this->assertSupported($type);
        if ($type === 'contact_phone') {
            $contact = Contact::query()->where('portfolio_id', $portfolio->id)->lockForUpdate()->findOrFail($payload['contact_id']);

            return [
                'id' => $contact->id, 'name' => $contact->name, 'phone' => $contact->phone,
                'updated_at' => $contact->updated_at?->toISOString(),
            ];
        }
        if ($type === 'rent_payment') {
            return $this->paymentSnapshot($portfolio, $payload['rent_charge_id']);
        }

        $property = $portfolio->properties()->whereKey($payload['property_id'])->lockForUpdate()->firstOrFail();
        $snapshot = [
            'id' => $property->id, 'name' => $property->name,
            'updated_at' => $property->updated_at?->toISOString(),
            'currency' => $portfolio->currency,
        ];
        if ($type === 'property_note') {
            // A content hash also detects edits within the same timestamp precision.
            $snapshot['notes_hash'] = $this->hash((string) $property->notes);
        }

        return $snapshot;
    }

    public function preview(string $type, array $payload, array $snapshot): array
    {
        $this->assertSupported($type);

        return match ($type) {
            'expense' => [
                'property' => ['id' => $snapshot['id'], 'name' => $snapshot['name']],
                'amount' => $payload['amount'], 'currency' => $snapshot['currency'],
                'category' => $payload['category'], 'description' => $payload['description'],
                'transaction_date' => $payload['transaction_date'], 'status' => $payload['status'],
            ],
            'contact_phone' => [
                'contact' => ['id' => $snapshot['id'], 'name' => $snapshot['name']],
                'previous_phone' => $snapshot['phone'], 'phone' => $payload['phone'],
            ],
            'rent_payment' => [
                'property' => ['id' => $snapshot['property']['id'], 'name' => $snapshot['property']['name']],
                'lease' => ['id' => $snapshot['lease']['id']],
                'rent_charge' => [
                    'id' => $snapshot['charge']['id'], 'period' => $snapshot['charge']['period'],
                    'due_date' => $snapshot['charge']['due_date'], 'remaining_amount' => $snapshot['remaining_amount'],
                ],
                'amount' => $payload['amount'], 'currency' => $snapshot['currency'],
                'transaction_date' => $payload['transaction_date'], 'payment_method' => $payload['payment_method'] ?? null,
            ],
            'property_note' => [
                'property' => ['id' => $snapshot['id'], 'name' => $snapshot['name']], 'note' => $payload['note'],
            ],
        };
    }

    public function execute(string $type, Portfolio $portfolio, User $user, array $payload): array
    {
        $this->assertSupported($type);
        if ($type === 'expense') {
            $transaction = $this->expense->execute($portfolio, $user, $payload);

            return ['transaction_id' => $transaction->id, 'path' => '/finance'];
        }
        if ($type === 'contact_phone') {
            $contact = $this->phone->execute($portfolio, $user, $payload);

            return ['contact_id' => $contact->id, 'path' => '/contacts'];
        }
        if ($type === 'rent_payment') {
            $transaction = $this->payment->execute($portfolio, $user, $payload);

            return [
                'transaction_id' => $transaction->id, 'rent_charge_id' => $transaction->rent_charge_id,
                'lease_id' => $transaction->lease_id, 'path' => '/leases/'.$transaction->lease_id,
            ];
        }
        $property = $this->note->execute($portfolio, $user, $payload);

        return ['property_id' => $property->id, 'path' => '/properties/'.$property->id];
    }

    private function paymentSnapshot(Portfolio $portfolio, int $chargeId): array
    {
        $charge = RentCharge::query()->where('portfolio_id', $portfolio->id)->lockForUpdate()->findOrFail($chargeId);
        $lease = Lease::query()->where('portfolio_id', $portfolio->id)->lockForUpdate()->findOrFail($charge->lease_id);
        $property = $portfolio->properties()->whereKey($lease->property_id)->lockForUpdate()->firstOrFail();
        $transactions = $charge->transactions()->where('portfolio_id', $portfolio->id)
            ->where('lease_id', $lease->id)->where('property_id', $property->id)
            ->where('direction', 'income')->where('category', 'rent')->where('status', 'paid')->orderBy('id')->lockForUpdate()
            ->get(['id', 'amount', 'transaction_date', 'payment_method']);
        $paid = $transactions->reduce(fn (string $sum, $transaction) => bcadd($sum, $transaction->amount, 2), '0.00');
        $remaining = bcsub($charge->amount, $paid, 2);

        return [
            'property' => [
                'id' => $property->id, 'name' => $property->name, 'updated_at' => $property->updated_at?->toISOString(),
            ],
            'lease' => [
                'id' => $lease->id, 'status' => $lease->status, 'start_date' => $lease->start_date?->toDateString(),
                'end_date' => $lease->end_date?->toDateString(), 'monthly_rent' => $lease->monthly_rent,
                'updated_at' => $lease->updated_at?->toISOString(),
            ],
            'charge' => [
                'id' => $charge->id, 'period' => $charge->period, 'due_date' => $charge->due_date->toDateString(),
                'amount' => $charge->amount, 'paid_amount' => $charge->paid_amount, 'status' => $charge->status,
                'updated_at' => $charge->updated_at?->toISOString(),
            ],
            'transactions_hash' => $this->hash($transactions->toJson()),
            'remaining_amount' => bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining,
            'currency' => $portfolio->currency,
        ];
    }

    private function assertSupported(string $type): void
    {
        abort_unless($this->supports($type), 409, 'Esta propuesta ya no es compatible. Prepara una nueva.');
    }

    private function hash(string $content): string
    {
        return hash_hmac('sha256', $content, (string) config('app.key'));
    }
}
