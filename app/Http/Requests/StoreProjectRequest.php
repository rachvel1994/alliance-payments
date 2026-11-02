<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class StoreProjectRequest extends BusinessRequest
{
    public function rules(): array
    {
        $city = $this->input('city');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('projects', 'name')
                ->where('city', is_string($city) ? $city : null)],
            'city' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'name.unique' => 'ამ ქალაქში ასეთი პროექტი უკვე არსებობს.'];
    }

    public function attributes(): array
    {
        return ['name' => 'პროექტის სახელი', 'city' => 'ქალაქი'];
    }
}
