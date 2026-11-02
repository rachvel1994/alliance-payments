<?php

declare(strict_types=1);

namespace App\Data;

final readonly class NewContractData
{
    public function __construct(
        public int $clientId,
        public int $apartmentId,
        public string $contractDate,
        public int $priceMinor,
        public string $firstDueDate,
        public int $installmentsCount,
    ) {}
}
