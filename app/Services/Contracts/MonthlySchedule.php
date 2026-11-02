<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class MonthlySchedule
{
    public function generate(int $priceMinor, int $count, string $firstDueDate): array
    {
        if ($count < 1 || $count > 360 || $priceMinor < $count) {
            throw new InvalidArgumentException('აირჩიე 1-დან 360-მდე შენატანი; თითოეულზე მინიმუმ 1 თეთრი უნდა ნაწილდებოდეს.');
        }

        $first = CarbonImmutable::createFromFormat('!Y-m-d', $firstDueDate);
        $amount = intdiv($priceMinor, $count);
        $remainder = $priceMinor % $count;
        $rows = [];
        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'sequence' => $index + 1,
                'due_date' => $first->addMonthsNoOverflow($index)->toDateString(),
                'amount_minor' => $amount + ($index === $count - 1 ? $remainder : 0),
            ];
        }

        return $rows;
    }
}
