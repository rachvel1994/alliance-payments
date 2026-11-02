<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Models\Project;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final class CreateProject
{
    public function handle(array $data): Project
    {
        try {
            return Project::query()->create(['name' => $data['name'], 'city' => $data['city']]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'ამ ქალაქში ასეთი პროექტი უკვე არსებობს.']);
        }
    }
}
