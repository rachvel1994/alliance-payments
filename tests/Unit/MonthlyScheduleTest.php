<?php

declare(strict_types=1);

use App\Services\Contracts\MonthlySchedule;

it('puts the exact remainder in the final monthly installment', function (): void {
    $rows = (new MonthlySchedule)->generate(10001, 3, '2026-01-31');

    expect(array_column($rows, 'amount_minor'))->toBe([3333, 3333, 3335])
        ->and(array_column($rows, 'due_date'))->toBe(['2026-01-31', '2026-02-28', '2026-03-31'])
        ->and(array_column($rows, 'sequence'))->toBe([1, 2, 3])
        ->and(array_sum(array_column($rows, 'amount_minor')))->toBe(10001);
});

it('keeps leap years and the original day of month', function (): void {
    $rows = (new MonthlySchedule)->generate(300, 3, '2028-01-31');
    expect(array_column($rows, 'due_date'))->toBe(['2028-01-31', '2028-02-29', '2028-03-31']);
});

it('preserves exact totals at the supported boundaries', function (int $price, int $count): void {
    $rows = (new MonthlySchedule)->generate($price, $count, '2026-12-31');
    expect($rows)->toHaveCount($count)
        ->and(array_sum(array_column($rows, 'amount_minor')))->toBe($price)
        ->and(min(array_column($rows, 'amount_minor')))->toBeGreaterThan(0);
})->with([[1, 1], [360, 360], [PHP_INT_MAX, 360]]);

it('rejects counts that would produce an invalid schedule', function (int $price, int $count): void {
    expect(fn () => (new MonthlySchedule)->generate($price, $count, '2026-01-01'))
        ->toThrow(InvalidArgumentException::class);
})->with([[100, 0], [100, -1], [1000, 361], [2, 3], [0, 1]]);
