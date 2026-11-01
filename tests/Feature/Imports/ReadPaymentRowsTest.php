<?php

declare(strict_types=1);

use App\Data\PaymentData;
use App\Services\Imports\ReadPaymentRows;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Tests\Support\PaymentSpreadsheet;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Storage::fake('local');
});

it('reads an actual XLSX while preserving leading zeroes and exact money', function (): void {
    $path = PaymentSpreadsheet::store([
        ['2026-02-01', '100.25', '01005098765', 'გიორგი ბერიძე', null, '0000123'],
    ]);
    $rows = app(ReadPaymentRows::class)->read($path);
    $data = PaymentData::fromArray($rows[0]);

    expect($rows)->toHaveCount(1)
        ->and($data->amountMinor)->toBe(10025)
        ->and($data->personalNumber)->toBe('01005098765')
        ->and($data->bankReference)->toBe('0000123')
        ->and($data->purpose)->toBeNull();
});

it('converts Excel serial dates into calendar dates', function (): void {
    $serial = Date::PHPToExcel(new DateTimeImmutable('2026-02-01 12:00:00'));
    $path = PaymentSpreadsheet::store([[$serial, 100, null, 'გადამხდელი', null, 'DATE-001']]);

    expect(app(ReadPaymentRows::class)->read($path)[0]['paid_at'])->toBe('2026-02-01');
});

it('accepts reordered columns and ignores unrelated columns', function (): void {
    $path = PaymentSpreadsheet::store([
        ['REF-1', 'გადამხდელი', '2026-02-01', null, 100, null, 'არ უნდა მოხვდეს გადახდაში'],
    ], ['bank_reference', 'payer_name', 'paid_at', 'purpose', 'amount', 'personal_number', 'internal_note']);
    $row = app(ReadPaymentRows::class)->read($path)[0];

    expect($row)->not->toHaveKey('internal_note')
        ->and(PaymentData::fromArray($row)->bankReference)->toBe('REF-1');
});

it('maps numeric identifier cells to strings without guessing missing digits', function (): void {
    $path = PaymentSpreadsheet::store([
        ['2026-02-01', 100, 11111111111, 'გადამხდელი', null, 123456],
        ['2026-02-01', 100, 12345, 'გადამხდელი', null, 123457],
    ]);
    $rows = app(ReadPaymentRows::class)->read($path);

    expect($rows[0]['personal_number'])->toBe('11111111111')
        ->and($rows[0]['bank_reference'])->toBe('123456')
        ->and($rows[1]['personal_number'])->toBe('12345');
    expect(fn () => PaymentData::fromArray($rows[1]))->toThrow(ValidationException::class);
});

it('requires every payment heading including nullable fields', function (string $missing): void {
    $headings = array_values(array_diff(
        ['paid_at', 'amount', 'personal_number', 'payer_name', 'purpose', 'bank_reference'], [$missing],
    ));
    $path = PaymentSpreadsheet::store([array_fill(0, 5, 'value')], $headings);

    expect(fn () => app(ReadPaymentRows::class)->read($path))
        ->toThrow(InvalidArgumentException::class, 'ფაილში ექვსივე აუცილებელი სვეტის სათაური უნდა იყოს.');
})->with(['paid_at', 'amount', 'personal_number', 'payer_name', 'purpose', 'bank_reference']);

it('rejects empty spreadsheets and heading-only spreadsheets', function (array $headings): void {
    $path = PaymentSpreadsheet::store([], $headings);

    expect(fn () => app(ReadPaymentRows::class)->read($path))
        ->toThrow(InvalidArgumentException::class, 'ფაილი უნდა შეიცავდეს ერთ ფურცელს, სათაურებსა და მონაცემებს.');
})->with([
    'empty' => [[]],
    'only headings' => [['paid_at', 'amount', 'personal_number', 'payer_name', 'purpose', 'bank_reference']],
]);

it('rejects workbooks with multiple worksheets', function (): void {
    $export = new class implements WithMultipleSheets
    {
        public function sheets(): array
        {
            $sheet = new class implements FromArray
            {
                public function array(): array
                {
                    return [
                        ['paid_at', 'amount', 'personal_number', 'payer_name', 'purpose', 'bank_reference'],
                        ['2026-02-01', 100, null, 'გადამხდელი', null, 'MULTI-1'],
                    ];
                }
            };

            return [$sheet, $sheet];
        }
    };
    Storage::disk('local')->put('multiple.xlsx', app(Excel::class)->raw($export, Excel::XLSX));

    expect(fn () => app(ReadPaymentRows::class)->read('multiple.xlsx'))
        ->toThrow(InvalidArgumentException::class, 'ფაილი უნდა შეიცავდეს ერთ ფურცელს, სათაურებსა და მონაცემებს.');
});

it('accepts two thousand data rows', function (): void {
    $path = PaymentSpreadsheet::store(array_fill(0, 2000, ['2026-02-01', 100, null, 'გადამხდელი', null, 'LIMIT']));

    expect(app(ReadPaymentRows::class)->read($path))->toHaveCount(2000);
});

it('rejects a spreadsheet that exceeds the row limit', function (): void {
    $path = PaymentSpreadsheet::store(array_fill(0, 2001, ['2026-02-01', 100, null, 'გადამხდელი', null, 'LIMIT']));

    expect(fn () => app(ReadPaymentRows::class)->read($path))
        ->toThrow(InvalidArgumentException::class, 'ფაილში დასაშვებია მაქსიმუმ 2000 მონაცემთა სტრიქონი.');
});

it('leaves invalid dates for per-row validation and keeps physical row positions', function (): void {
    $path = PaymentSpreadsheet::store([
        [0, 100, null, 'გადამხდელი', null, 'INVALID-DATE'],
        [null, null, null, null, null, null],
        ['2026-02-01', 100, null, 'გადამხდელი', null, 'AFTER-BLANK'],
    ]);
    $rows = app(ReadPaymentRows::class)->read($path);

    expect($rows)->toHaveCount(3)
        ->and($rows[0]['paid_at'])->toBe(0)
        ->and($rows[1]['amount'])->toBeNull()
        ->and($rows[2]['bank_reference'])->toBe('AFTER-BLANK');
});

it('rejects corrupt XLSX content with a Georgian message', function (): void {
    Storage::disk('local')->put('corrupt.xlsx', 'not a spreadsheet');

    expect(fn () => app(ReadPaymentRows::class)->read('corrupt.xlsx'))
        ->toThrow(InvalidArgumentException::class, 'Excel ფაილის წაკითხვა ვერ მოხერხდა.');
});

it('keeps formulas unevaluated so payment validation rejects them', function (): void {
    $path = PaymentSpreadsheet::store([['2026-02-01', '=1+1', null, 'გადამხდელი', null, 'FORMULA']]);
    $row = app(ReadPaymentRows::class)->read($path)[0];

    expect($row['amount'])->toBe('=1+1');
    expect(fn () => PaymentData::fromArray($row))->toThrow(ValidationException::class);
});
