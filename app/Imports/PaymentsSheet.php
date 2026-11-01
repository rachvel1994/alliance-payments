<?php

declare(strict_types=1);

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;

final class PaymentsSheet implements WithHeadingRow, WithLimit
{
    public function headingRow(): int
    {
        return 1;
    }

    public function limit(): int
    {
        // WithHeadingRow-ის საზღვარზე დამატებითი ცარიელი რიგის ასაცილებლად.
        return 2002;
    }
}
