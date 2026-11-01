<?php

declare(strict_types=1);

namespace App\Contracts;

interface AllocationStrategy
{
    /** @return list<array{payment_id:int, installment_id:int, amount_minor:int}> */
    public function allocate(array $payments, array $installments): array;
}
