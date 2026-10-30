<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\Exception\MathException;
use Brick\Money\Money as BrickMoney;
use InvalidArgumentException;

final class Money
{
    public static function toMinor(int|float|string $value): int
    {
        try {
            $money = BrickMoney::of((string) $value, 'GEL');

            if (! $money->isPositive()) {
                throw new InvalidArgumentException('თანხა 0 მეტი უნდა იყოს.');
            }

            return $money->getMinorAmount()->toInt();

        } catch (MathException $exception) {
            throw new InvalidArgumentException('თანხა არასწორია ან თეთრებში ზუსტად ვერ გამოისახება.', previous: $exception);
        }
    }
}
