<?php

namespace App\Domain\Properties\Actions;

use App\Domain\Portfolio\Models\Portfolio;
use App\Domain\Portfolio\Services\PropertyAccess;
use App\Domain\Properties\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Appends a note without allowing an assistant to replace existing property notes. */
final class AppendPropertyNote
{
    public function __construct(private readonly PropertyAccess $properties) {}

    public function validatedData(Portfolio $portfolio, User $user, array $input): array
    {
        abort_unless($portfolio->members()->whereKey($user->id)->wherePivot('role', 'owner')->exists(), 404);
        if (is_string($input['note'] ?? null)) {
            $input['note'] = trim($input['note']);
        }
        $data = Validator::make($input, [
            'property_id' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', 'max:2000'],
        ])->validate();
        $this->properties->assertWritable($portfolio, (int) $data['property_id']);
        $property = $portfolio->properties()->findOrFail($data['property_id']);
        $this->appendedNotes($property, $data['note']);
        $data['property_id'] = (int) $data['property_id'];

        return $data;
    }

    public function execute(Portfolio $portfolio, User $user, array $input): Property
    {
        return DB::transaction(function () use ($portfolio, $user, $input) {
            $portfolio = Portfolio::query()->lockForUpdate()->findOrFail($portfolio->id);
            $data = $this->validatedData($portfolio, $user, $input);
            $property = $portfolio->properties()->lockForUpdate()->findOrFail($data['property_id']);
            $property->update(['notes' => $this->appendedNotes($property, $data['note'])]);

            return $property;
        });
    }

    private function appendedNotes(Property $property, string $note): string
    {
        $existing = (string) $property->notes;
        $result = $existing === '' ? $note : $existing."\n\n".$note;
        if (mb_strlen($result) > 10000) {
            throw ValidationException::withMessages(['note' => ['Las notas del inmueble no pueden superar los 10.000 caracteres en total.']]);
        }

        return $result;
    }
}
