<?php

namespace App\Domain\Fiscality\Services;

use InvalidArgumentException;

final class TaxMoney
{
    /** Validated unsigned decimal input to cents (also used for percentage basis points). */
    public static function cents(string|int $value): int
    {
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', (string) $value)) {
            throw new InvalidArgumentException('Invalid fiscal decimal.');
        }
        [$whole, $fraction] = array_pad(explode('.', (string) $value), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    /** Half-up rounding once at each reported line; integer arithmetic only. */
    public static function ratio(int $cents, int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Invalid fiscal denominator.');
        }
        $rounded = (int) bcdiv(bcadd(bcmul((string) abs($cents), (string) $numerator, 0), (string) intdiv($denominator, 2), 0), (string) $denominator, 0);

        return $cents < 0 ? -$rounded : $rounded;
    }
}
