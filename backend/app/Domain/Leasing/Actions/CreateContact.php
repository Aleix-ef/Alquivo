<?php

namespace App\Domain\Leasing\Actions;

use App\Domain\Leasing\Models\Contact;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CreateContact
{
    public function validatedData(Portfolio $portfolio, User $user, array $input): array
    {
        abort_unless($portfolio->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists(), 404);
        foreach (['name', 'email', 'phone'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = trim($input[$key]);
            }
        }

        return Validator::make($input, [
            'kind' => ['required', Rule::in(['person', 'company'])],
            'name' => ['required', 'string', 'max:120'],
            'tax_id' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'], 'notes' => ['nullable', 'string', 'max:10000'],
        ])->validate();
    }

    public function execute(Portfolio $portfolio, User $user, array $input): Contact
    {
        return DB::transaction(function () use ($portfolio, $user, $input) {
            $portfolio = Portfolio::whereKey($portfolio->id)->lockForUpdate()->firstOrFail();

            return Contact::create([...$this->validatedData($portfolio, $user, $input), 'portfolio_id' => $portfolio->id]);
        });
    }
}
