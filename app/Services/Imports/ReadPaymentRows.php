<?php

declare(strict_types=1);

namespace App\Services\Imports;

use App\Imports\PaymentsSheet;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Shared\Date;

final readonly class ReadPaymentRows
{
    private const HEADINGS = [
        'paid_at', 'amount', 'personal_number', 'payer_name', 'purpose', 'bank_reference',
    ];

    public function __construct(private Excel $excel) {}

    public function read(string $path): array
    {
        try {
            $sheets = $this->excel->toArray(new PaymentsSheet, $path, 'local', Excel::XLSX);
        } catch (SpreadsheetException $error) {
            throw new InvalidArgumentException('Excel ფაილის წაკითხვა ვერ მოხერხდა.', previous: $error);
        }

        if (count($sheets) !== 1 || $sheets[0] === []) {
            throw new InvalidArgumentException('ფაილი უნდა შეიცავდეს ერთ ფურცელს, სათაურებსა და მონაცემებს.');
        }

        $rows = $sheets[0];
        if (count($rows) > 2000) {
            throw new InvalidArgumentException('ფაილში დასაშვებია მაქსიმუმ 2000 მონაცემთა სტრიქონი.');
        }
        if (array_diff(self::HEADINGS, array_keys($rows[0])) !== []) {
            throw new InvalidArgumentException('ფაილში ექვსივე აუცილებელი სვეტის სათაური უნდა იყოს.');
        }

        $headings = array_flip(self::HEADINGS);
        foreach ($rows as $index => $row) {
            $row = array_intersect_key($row, $headings);
            $date = $row['paid_at'];
            if (is_numeric($date) && (float) $date >= 1 && (float) $date <= 2958465) {
                $row['paid_at'] = Date::excelToDateTimeObject((float) $date)->format('Y-m-d');
            }
            foreach (['personal_number', 'bank_reference'] as $key) {
                if (is_numeric($row[$key])) {
                    $row[$key] = (string) $row[$key];
                }
            }
            $rows[$index] = $row;
        }

        return $rows;
    }
}
