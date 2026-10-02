<?php

namespace App\Support;

final class CsvCell
{
    public static function sanitize(mixed $value): mixed
    {
        // Spreadsheet programs may ignore leading whitespace/control characters.
        // Keep the original text; the apostrophe makes it a literal, not a formula.
        if (is_string($value) && preg_match('/^[\s\p{Cf}\x00-\x1f]*[=+@\-]/u', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
