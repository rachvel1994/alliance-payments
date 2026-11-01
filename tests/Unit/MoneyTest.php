<?php

declare(strict_types=1);

use App\Support\Money;

it('formats integer tetri without float rounding', function (int|string $minor, string $formatted): void {
    expect(Money::formatMinor($minor))->toBe($formatted);
})->with([
    'zero' => [0, '0.00'],
    'one tetri' => [1, '0.01'],
    'ordinary amount' => [942857, '9428.57'],
    'largest PHP integer' => [PHP_INT_MAX, '92233720368547758.07'],
    'large aggregate' => ['18446744073709551615', '184467440737095516.15'],
]);

it('converts GEL inputs to exact integer tetri', function (int|float|string $input, int $expected): void {
    expect(Money::toMinor($input))->toBe($expected);
})->with([
    'one tetri' => ['0.01', 1],
    'whole GEL string' => ['50', 5000],
    'payment fixture precision' => ['9428.57', 942857],
    'last installment precision' => ['9428.58', 942858],
    'integer input' => [50, 5000],
    'float input from Excel' => [9428.57, 942857],
    'decimal float input' => [0.29, 29],
    'exact trailing decimal' => ['1.230', 123],
    'leading zeroes' => ['050.00', 5000],
    'large amount without an arbitrary limit' => ['100000000000.00', 10000000000000],
]);

it('rejects invalid or imprecise amounts without rounding them', function (int|float|string $input): void {
    expect(fn () => Money::toMinor($input))->toThrow(InvalidArgumentException::class);
})->with([
    'text' => ['not money'],
    'comma decimal separator' => ['50,25'],
    'negative amount' => ['-50.00'],
    'zero string' => ['0.00'],
    'sub-tetri string' => ['1.005'],
    'integer overflow' => ['999999999999999999.99'],
]);
