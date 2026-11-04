<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final readonly class ReportingPeriod
{
    public function __construct(public ?string $from, public string $to) {}

    public static function fromFilters(array $filters): self
    {
        return new self($filters['date_from'] ?? null, $filters['as_of'] ?? now()->toDateString());
    }

    public function apply(Builder $query, string $column): Builder
    {
        return $query->whereDate($column, '<=', $this->to)
            ->when($this->from !== null, fn (Builder $query) => $query->whereDate($column, '>=', $this->from));
    }

    public function parameters(): array
    {
        return array_filter(['as_of' => $this->to, 'date_from' => $this->from]);
    }
}
