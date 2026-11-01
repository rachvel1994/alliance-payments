<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Apartment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class WorkspaceQuery
{
    public function apartments(array $filters): array
    {
        $query = Apartment::query()->with('project');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        if (($term = $filters['q'] ?? '') !== '') {
            $query->where(fn (Builder $query) => $query
                ->whereLike('number', '%'.$term.'%')
                ->orWhereHas('project', fn (Builder $project) => $project
                    ->whereLike('name', '%'.$term.'%')->orWhereLike('city', '%'.$term.'%')));
        }

        return $this->page($query, 'apartments', $filters, fn (Apartment $apartment): array => [
            'id' => $apartment->id,
            'project' => $apartment->project->name,
            'city' => $apartment->project->city,
            'number' => $apartment->number,
            'floor' => $apartment->floor,
            'area' => $apartment->area,
            'price_GEL' => Money::formatMinor($apartment->price_minor),
            'status' => $apartment->status,
        ], ['id' => 'id', 'price' => 'price_minor'], 'id');
    }

    private function date(array $filters): string
    {
        return $filters['as_of'] ?? now()->toDateString();
    }

    private function page(
        Builder $query,
        string $section,
        array $filters,
        callable $map,
        array $sorts,
        string $default,
        array $summary = [],
        ?string $importStatus = null,
    ): array {
        $sort = $sorts[$filters['sort'] ?? ''] ?? $default;
        $paginator = $query->orderBy($sort, ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc')
            ->orderBy($query->getModel()->qualifyColumn('id'))
            ->paginate(20, ['*'], 'page', (int) ($filters['page'] ?? 1))->appends($filters);
        $paginator->setCollection($paginator->getCollection()->map($map));

        return $this->props($section, $filters, $paginator, $summary, $importStatus);
    }

    private function props(
        string $section,
        array $filters,
        LengthAwarePaginator $paginator,
        array $summary = [],
        ?string $importStatus = null,
    ): array {
        return [
            'section' => $section,
            'filters' => [...$filters, 'as_of' => $this->date($filters)],
            'rows' => $paginator->items(),
            'links' => $paginator->linkCollection()->all(),
            'total' => $paginator->total(),
            'summary' => $summary,
            'importStatus' => $importStatus,
        ];
    }

    private function href(string $path, array $query = []): string
    {
        return url($path).($query === [] ? '' : '?'.http_build_query($query));
    }
}
