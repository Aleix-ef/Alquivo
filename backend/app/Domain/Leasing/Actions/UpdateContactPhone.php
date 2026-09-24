<?php

namespace App\Domain\Leasing\Actions;

use App\Domain\Leasing\Models\Contact;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Shared phone validation for the manual form and explicitly confirmed AI proposals. */
final class UpdateContactPhone
{
    public function validatedData(Portfolio $portfolio, User $user, array $input): array
    {
        abort_unless($portfolio->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists(), 404);
        if (is_string($input['phone'] ?? null)) {
            $input['phone'] = trim($input['phone']);
        }
        $data = Validator::make($input, [
            'contact_id' => ['required', 'integer', 'min:1'],
            // Null is an explicit manual clear. AI proposal schemas do not allow clearing.
            'phone' => ['present', 'nullable', Rule::requiredIf(array_key_exists('phone', $input) && $input['phone'] !== null), 'string', 'max:30', function ($attribute, $value, $fail) {
                $digits = preg_replace('/[^0-9]/', '', $value);
                if (! preg_match('/^\+?[0-9 ().-]+$/D', $value) || strlen($digits) < 7 || strlen($digits) > 15) {
                    $fail('Introduce un teléfono válido de entre 7 y 15 dígitos.');
                }
            }],
        ])->validate();
        Contact::query()->where('portfolio_id', $portfolio->id)->findOrFail($data['contact_id']);
        $data['contact_id'] = (int) $data['contact_id'];

        return $data;
    }

    public function execute(Portfolio $portfolio, User $user, array $input): Contact
    {
        return DB::transaction(function () use ($portfolio, $user, $input) {
            $portfolio = Portfolio::query()->lockForUpdate()->findOrFail($portfolio->id);
            $data = $this->validatedData($portfolio, $user, $input);
            $contact = Contact::query()->where('portfolio_id', $portfolio->id)
                ->lockForUpdate()->findOrFail($data['contact_id']);
            $contact->update(['phone' => $data['phone']]);

            return $contact;
        });
    }
}
