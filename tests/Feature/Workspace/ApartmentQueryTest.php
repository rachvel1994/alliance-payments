<?php

declare(strict_types=1);

use App\Http\Requests\WorkspaceRequest;
use App\Models\Apartment;
use App\Models\Project;
use App\Queries\WorkspaceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('lists apartments with their project and exact GEL price', function (): void {
    $apartment = Apartment::factory()->create(['price_minor' => 942857, 'status' => 'free']);

    $result = app(WorkspaceQuery::class)->apartments([]);

    expect($result['section'])->toBe('apartments')
        ->and($result['total'])->toBe(1)
        ->and($result['rows'][0]['id'])->toBe($apartment->id)
        ->and($result['rows'][0]['project'])->toBe($apartment->project->name)
        ->and($result['rows'][0]['price_GEL'])->toBe('9428.57')
        ->and($result['rows'][0]['status'])->toBe('free');
});

it('keeps search OR clauses inside the project and status filters', function (): void {
    $project = Project::factory()->create(['name' => 'Alpha', 'city' => 'Tbilisi']);
    Apartment::factory()->for($project)->create(['number' => 'A-1', 'status' => 'free']);
    Apartment::factory()->create(['number' => 'SEARCH', 'status' => 'sold']);

    $result = app(WorkspaceQuery::class)->apartments([
        'project_id' => $project->id, 'status' => 'free', 'q' => 'SEARCH',
    ]);

    expect($result['total'])->toBe(0);
});

it('searches by apartment number project and city including zero', function (string $term): void {
    $project = Project::factory()->create(['name' => 'Sea Tower', 'city' => 'Batumi']);
    $target = Apartment::factory()->for($project)->create(['number' => 'A-0']);
    $other = Project::factory()->create(['name' => 'Other', 'city' => 'Tbilisi']);
    Apartment::factory()->for($other)->create(['number' => 'B-1']);

    $result = app(WorkspaceQuery::class)->apartments(['q' => $term]);

    expect($result['total'])->toBe(1)->and($result['rows'][0]['id'])->toBe($target->id);
})->with(['number' => ['A-0'], 'project case insensitive' => ['sea'], 'city' => ['BATUMI'], 'zero' => ['0']]);

it('paginates and orders prices before using the stable ID tie breaker', function (): void {
    $project = Project::factory()->create();
    $apartments = Apartment::factory()->count(21)->for($project)->create(['price_minor' => 10000, 'status' => 'free']);
    $result = app(WorkspaceQuery::class)->apartments([
        'sort' => 'price', 'direction' => 'desc', 'page' => 2, 'status' => 'free',
    ]);

    expect($result['total'])->toBe(21)
        ->and($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['id'])->toBe($apartments->last()->id)
        ->and($result['filters']['status'])->toBe('free');
});

it('validates workspace filters using Georgian messages', function (string $field, mixed $value): void {
    $request = new WorkspaceRequest;
    $validator = Validator::make([$field => $value], $request->rules(), $request->messages(), $request->attributes());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first($field))->toMatch('/[ა-ჰ]/u');
})->with([
    'search type' => ['q', []],
    'status' => ['status', 'invalid'],
    'project' => ['project_id', 999999],
    'client' => ['client_id', 999999],
    'date' => ['as_of', '2026-02-30'],
    'sort' => ['sort', 'price; drop table apartments'],
    'direction' => ['direction', 'sideways'],
    'page' => ['page', 0],
]);
