<?php

declare(strict_types=1);

use App\Data\PaymentData;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

function paymentDataInput(array $overrides = []): array
{
    return array_replace([
        'paid_at' => '2026-02-01',
        'amount' => '9428.57',
        'personal_number' => '01005098765',
        'payer_name' => 'გიორგი ბერიძე',
        'purpose' => 'განვადება',
        'bank_reference' => 'TB260201-0017',
    ], $overrides);
}

it('normalizes a spreadsheet row and maps validated payment attributes', function (): void {
    $data = PaymentData::fromArray(paymentDataInput([
        'paid_at' => ' 2026-02-01 ',
        'amount' => ' 9428.57 ',
        'personal_number' => ' 01005098765 ',
        'payer_name' => ' გიორგი ბერიძე ',
        'purpose' => " \t ",
        'bank_reference' => ' TB260201-0017 ',
        'client_id' => 999,
        'amount_minor' => 1,
    ]));

    expect($data->bankReference)->toBe('TB260201-0017')
        ->and($data->attributes())->toBe([
            'paid_at' => '2026-02-01',
            'amount_minor' => 942857,
            'personal_number' => '01005098765',
            'payer_name' => 'გიორგი ბერიძე',
            'purpose' => null,
        ]);
});

it('preserves zero strings and leading zeroes', function (): void {
    $data = PaymentData::fromArray(paymentDataInput([
        'personal_number' => '00000000000',
        'purpose' => ' 0 ',
        'bank_reference' => ' 0 ',
    ]));

    expect($data->personalNumber)->toBe('00000000000')
        ->and($data->purpose)->toBe('0')
        ->and($data->bankReference)->toBe('0');
});

it('allows missing optional fields and future payment dates', function (): void {
    $input = paymentDataInput(['paid_at' => '2027-01-01']);
    unset($input['personal_number'], $input['purpose']);

    $data = PaymentData::fromArray($input);

    expect($data->paidAt)->toBe('2027-01-01')
        ->and($data->personalNumber)->toBeNull()
        ->and($data->purpose)->toBeNull();
});

it('accepts numeric amounts without converting them during string cleanup', function (int|float $amount, int $expected): void {
    $data = PaymentData::fromArray(paymentDataInput(['amount' => $amount]));

    expect($data->amountMinor)->toBe($expected);
})->with([
    [50, 5000],
    [9428.57, 942857],
]);

it('returns Georgian validation errors for invalid payment fields', function (string $field, mixed $value): void {
    try {
        PaymentData::fromArray(paymentDataInput([$field => $value]));
        $this->fail('არასწორი გადახდის მონაცემები ვალიდაციამ უნდა უარყოს.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field)
            ->and($exception->errors()[$field][0])->toMatch('/[ა-ჰ]/u');
    }
})->with([
    'blank date' => ['paid_at', '   '],
    'invalid calendar date' => ['paid_at', '2026-02-30'],
    'wrong date format' => ['paid_at', '01/02/2026'],
    'short personal number' => ['personal_number', '123'],
    'personal number as an integer' => ['personal_number', 61001055555],
    'payer name as an integer' => ['payer_name', 123],
    'blank payer name' => ['payer_name', '   '],
    'long payer name' => ['payer_name', str_repeat('ა', 256)],
    'purpose as an array' => ['purpose', ['invalid']],
    'long purpose' => ['purpose', str_repeat('ა', 2001)],
    'reference as a boolean' => ['bank_reference', true],
    'blank reference' => ['bank_reference', ' '],
    'long reference' => ['bank_reference', str_repeat('A', 256)],
    'missing amount' => ['amount', null],
    'amount as an array' => ['amount', [10]],
    'amount as a boolean' => ['amount', true],
    'amount as text' => ['amount', 'invalid'],
    'zero amount' => ['amount', '0'],
    'negative amount' => ['amount', '-1.00'],
    'fraction smaller than a tetri' => ['amount', '1.005'],
    'amount exceeding an integer' => ['amount', '999999999999999999.99'],
]);
