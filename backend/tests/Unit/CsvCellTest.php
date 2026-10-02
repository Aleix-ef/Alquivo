<?php

namespace Tests\Unit;

use App\Support\CsvCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvCellTest extends TestCase
{
    public static function formulas(): array
    {
        return array_map(fn ($value) => [$value], ['=1+1', '+1+1', '-1+1', '@SUM(1)', "\t=1+1", "\r=1+1", "\n+1", " \t\r@SUM(1)", "\x00=1", "\u{FEFF}=1", "\u{00A0}=1"]);
    }

    #[DataProvider('formulas')]
    public function test_formula_is_literal_even_after_whitespace_or_controls(string $value): void
    {
        $this->assertSame("'".$value, CsvCell::sanitize($value));
        $this->assertSame("'".$value, CsvCell::sanitize(CsvCell::sanitize($value)));
    }

    public function test_normal_text_and_numeric_values_are_unchanged(): void
    {
        foreach (['Alquiler', 'Piso - Valencia', '300.00', '', null, 12, -12, 12.25] as $value) {
            $this->assertSame($value, CsvCell::sanitize($value));
        }
    }
}
