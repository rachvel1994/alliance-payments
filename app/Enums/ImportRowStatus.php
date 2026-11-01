<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportRowStatus: string
{
    case Imported = 'imported';
    case Duplicate = 'duplicate';
    case Rejected = 'rejected';
}
