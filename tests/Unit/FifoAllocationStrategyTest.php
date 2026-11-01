<?php

declare(strict_types=1);

use App\Services\Payments\FifoAllocationStrategy;

it('allocates payments chronologically across the global installment due-date order', function (): void {
    $result = (new FifoAllocationStrategy)->allocate([
        ['id' => 2, 'paid_at' => '2026-02-02', 'amount_minor' => 8000],
        ['id' => 1, 'paid_at' => '2026-02-01', 'amount_minor' => 7000],
    ], [
        ['id' => 20, 'contract_id' => 2, 'due_date' => '2026-02-01', 'amount_minor' => 9000],
        ['id' => 10, 'contract_id' => 1, 'due_date' => '2026-01-01', 'amount_minor' => 6000],
    ]);

    expect($result)->toBe([
        ['payment_id' => 1, 'installment_id' => 10, 'amount_minor' => 6000],
        ['payment_id' => 1, 'installment_id' => 20, 'amount_minor' => 1000],
        ['payment_id' => 2, 'installment_id' => 20, 'amount_minor' => 8000],
    ]);
});

it('breaks date ties by numeric IDs and reindexes associative input arrays', function (): void {
    $result = (new FifoAllocationStrategy)->allocate([
        'second' => ['id' => '2', 'paid_at' => '2026-01-01', 'amount_minor' => '5000'],
        'first' => ['id' => '1', 'paid_at' => '2026-01-01', 'amount_minor' => '5000'],
    ], [
        40 => ['id' => '20', 'due_date' => '2026-01-01', 'amount_minor' => '5000'],
        7 => ['id' => '3', 'due_date' => '2026-01-01', 'amount_minor' => '5000'],
    ]);

    expect($result)->toBe([
        ['payment_id' => 1, 'installment_id' => 3, 'amount_minor' => 5000],
        ['payment_id' => 2, 'installment_id' => 20, 'amount_minor' => 5000],
    ]);
});

it('leaves surplus payments unallocated after every installment is covered', function (): void {
    $result = (new FifoAllocationStrategy)->allocate([
        ['id' => 1, 'paid_at' => '2026-01-01', 'amount_minor' => 17000],
        ['id' => 2, 'paid_at' => '2026-02-01', 'amount_minor' => 5000],
    ], [
        ['id' => 1, 'due_date' => '2026-01-01', 'amount_minor' => 10000],
    ]);

    expect($result)->toBe([
        ['payment_id' => 1, 'installment_id' => 1, 'amount_minor' => 10000],
    ]);
});

it('covers a single installment with multiple partial payments', function (): void {
    $result = (new FifoAllocationStrategy)->allocate([
        ['id' => 1, 'paid_at' => '2026-01-01', 'amount_minor' => 3000],
        ['id' => 2, 'paid_at' => '2026-02-01', 'amount_minor' => 4000],
    ], [
        ['id' => 1, 'due_date' => '2026-01-01', 'amount_minor' => 10000],
    ]);

    expect($result)->toBe([
        ['payment_id' => 1, 'installment_id' => 1, 'amount_minor' => 3000],
        ['payment_id' => 2, 'installment_id' => 1, 'amount_minor' => 4000],
    ]);
});

it('skips nonpositive payment and installment amounts', function (): void {
    $result = (new FifoAllocationStrategy)->allocate([
        ['id' => 1, 'paid_at' => '2026-01-01', 'amount_minor' => 0],
        ['id' => 2, 'paid_at' => '2026-01-01', 'amount_minor' => -100],
        ['id' => 3, 'paid_at' => '2026-01-01', 'amount_minor' => 3000],
    ], [
        ['id' => 1, 'due_date' => '2026-01-01', 'amount_minor' => 0],
        ['id' => 2, 'due_date' => '2026-01-01', 'amount_minor' => -100],
        ['id' => 3, 'due_date' => '2026-01-01', 'amount_minor' => 5000],
    ]);

    expect($result)->toBe([
        ['payment_id' => 3, 'installment_id' => 3, 'amount_minor' => 3000],
    ]);
});

it('keeps one-tetri remainders exact', function (): void {
    $result = (new FifoAllocationStrategy)->allocate([
        ['id' => 1, 'paid_at' => '2026-01-01', 'amount_minor' => 942857],
    ], [
        ['id' => 1, 'due_date' => '2026-01-01', 'amount_minor' => 942856],
        ['id' => 2, 'due_date' => '2026-02-01', 'amount_minor' => 2],
    ]);

    expect($result)->toBe([
        ['payment_id' => 1, 'installment_id' => 1, 'amount_minor' => 942856],
        ['payment_id' => 1, 'installment_id' => 2, 'amount_minor' => 1],
    ]);
});

it('is repeatable and does not mutate its input arrays', function (): void {
    $payments = [['id' => 1, 'paid_at' => '2026-01-01', 'amount_minor' => 10000]];
    $installments = [['id' => 1, 'due_date' => '2026-01-01', 'amount_minor' => 15000]];
    $originalPayments = $payments;
    $originalInstallments = $installments;
    $strategy = new FifoAllocationStrategy;
    $result = $strategy->allocate($payments, $installments);

    expect($strategy->allocate($payments, $installments))->toBe($result)
        ->and($payments)->toBe($originalPayments)
        ->and($installments)->toBe($originalInstallments);
});

it('returns no allocations when either side is empty', function (array $payments, array $installments): void {
    expect((new FifoAllocationStrategy)->allocate($payments, $installments))->toBe([]);
})->with([
    'both empty' => [[], []],
    'no installments' => [[['id' => 1, 'paid_at' => '2026-01-01', 'amount_minor' => 100]], []],
    'no payments' => [[], [['id' => 1, 'due_date' => '2026-01-01', 'amount_minor' => 100]]],
]);
