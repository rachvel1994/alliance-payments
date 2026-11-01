<?php

declare(strict_types=1);

namespace App\Enums;

enum InstallmentStatus: string
{
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Partial = 'partial';
    case Pending = 'pending';
}
