<?php

namespace Tests\Unit;

use App\Support\BolivianNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BolivianNumberTest extends TestCase
{
    #[DataProvider('numbers')]
    public function test_it_uses_dots_for_thousands_and_a_comma_for_decimals(
        int|float|string|null $value,
        int $decimals,
        string $expected,
    ): void {
        $this->assertSame($expected, BolivianNumber::format($value, $decimals));
    }

    public static function numbers(): array
    {
        return [
            'integer' => [1234567, 0, '1.234.567'],
            'money' => [1234567.8, 2, '1.234.567,80'],
            'weight' => ['12345.678', 3, '12.345,678'],
            'negative' => [-9876.5, 2, '-9.876,50'],
            'empty value' => [null, 2, '0,00'],
        ];
    }
}
