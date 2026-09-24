<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\Transaction;
use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PropertyAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** The manual form and the assistant share this financial write boundary. */
final class CreateExpense
{
    public function __construct(private readonly PropertyAccess $properties) {}

    public function validatedData(Portfolio $portfolio, User $user, array $input): array
    {
        $this->authorize($portfolio, $user);
        $data = Validator::make($input, [
            'property_id' => ['nullable', 'integer', 'min:1'],
            'category' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:180'],
            // Do not round, accept exponents or perform money arithmetic using floats.
            'amount' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D', 'numeric', 'gt:0', 'max:9999999999.99'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in(['pending', 'paid', 'cancelled'])],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ])->validate();

        if (! empty($data['property_id'])) {
            $this->properties->assertWritable($portfolio, (int) $data['property_id']);
            $data['property_id'] = (int) $data['property_id'];
        }

        // Canonical decimal string without a float conversion, including for integer input.
        [$whole, $fraction] = array_pad(explode('.', (string) $data['amount']), 2, '');
        $data['amount'] = ((string) (int) $whole).'.'.str_pad($fraction, 2, '0');
        $data['description'] = trim($data['description']);

        return $data;
    }

    public function execute(Portfolio $portfolio, User $user, array $input): Transaction
    {
        return DB::transaction(function () use ($portfolio, $user, $input) {
            // Also serialize with AI confirmations. No network calls take place in this transaction.
            $portfolio = Portfolio::query()->lockForUpdate()->findOrFail($portfolio->id);
            $data = $this->validatedData($portfolio, $user, $input);
            if (! empty($data['property_id'])) {
                $portfolio->properties()->whereKey($data['property_id'])->lockForUpdate()->firstOrFail();
            }

            return Transaction::create([
                ...$data, 'portfolio_id' => $portfolio->id, 'direction' => 'expense',
            ]);
        });
    }

    private function authorize(Portfolio $portfolio, User $user): void
    {
        abort_unless($portfolio->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists(), 404);
    }
}
