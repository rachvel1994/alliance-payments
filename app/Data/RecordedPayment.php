<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Payment;

final readonly class RecordedPayment
{
    public function __construct(
        public Payment $payment,
        public bool    $created,
    )
    {
    }
}
