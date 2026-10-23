<?php

namespace App\Domain\Leasing\Services;

use App\Domain\Leasing\Models\Lease;
use Illuminate\Validation\ValidationException;

/** payment_day remains the inclusive deadline; a missing start means that one day. */
final class LeasePaymentWindow
{
    public static function rules(bool $required = false): array
    {
        return [
            'payment_day' => [$required ? 'required' : 'sometimes', 'integer', 'between:1,31'],
            'payment_day_start' => ['sometimes', 'integer', 'between:1,31'],
        ];
    }

    public static function normalize(array $data, ?Lease $existing = null): array
    {
        $hasEnd = array_key_exists('payment_day', $data);
        $hasStart = array_key_exists('payment_day_start', $data);
        if (! $hasEnd && ! $hasStart) {
            return $data;
        }
        $end = $hasEnd ? (int) $data['payment_day'] : (int) $existing?->payment_day;
        // A legacy client changing only the day still requests a single-day window.
        $start = $hasStart ? (int) $data['payment_day_start'] : $end;
        if ($start > $end || $end < 1) {
            throw ValidationException::withMessages([
                'payment_day_start' => ['El primer día de cobro no puede ser posterior al último.'],
            ]);
        }
        if ($hasEnd) {
            $data['payment_day'] = $end;
        }
        // Keep legacy AI/import canonical payloads unchanged when they omit a start.
        if ($hasStart || $existing !== null) {
            $data['payment_day_start'] = $start;
        }

        return $data;
    }
}
