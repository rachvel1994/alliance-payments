<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Excel;

final class PaymentSpreadsheet
{
    public static function bytes(array $rows, ?array $headings = null): string
    {
        $headings ??= ['paid_at', 'amount', 'personal_number', 'payer_name', 'purpose', 'bank_reference'];
        $export = new class([$headings, ...$rows]) implements FromArray, WithStrictNullComparison
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        };

        return app(Excel::class)->raw($export, Excel::XLSX);
    }

    public static function store(array $rows, ?array $headings = null): string
    {
        $path = 'payment-imports/'.Str::uuid().'.xlsx';
        Storage::disk('local')->put($path, self::bytes($rows, $headings));

        return $path;
    }

    public static function upload(array $rows, string $name = 'payments.xlsx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::bytes($rows));
    }
}
