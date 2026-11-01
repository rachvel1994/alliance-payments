<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;

final class ListClients
{
    public function handle(array $filters): array
    {
        $term = $filters['q'] ?? '';
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';
        $query = Client::query()
            ->select(['id', 'name', 'personal_number', 'phone'])
            ->withCount('contracts');

        if ($term !== '') {
            $query->where(fn (Builder $query) => $query
                ->whereLike('name', '%'.$term.'%')
                ->orWhereLike('personal_number', '%'.$term.'%'));
        }

        return [
            'filters' => ['q' => $term, 'sort' => $sort, 'direction' => $direction],
            'clients' => $query->orderBy($sort, $direction)->orderBy('id')
                ->paginate(20, page: $filters['page'] ?? 1)->appends($filters),
        ];
    }
}
