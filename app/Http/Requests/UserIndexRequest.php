<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UserIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', Rule::in(array_keys(config('access.roles')))],
            'page' => ['nullable', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ['string' => 'საძიებო ტექსტი უნდა იყოს ტექსტი.', 'max' => 'ძიება არ უნდა აღემატებოდეს :max სიმბოლოს.',
            'in' => 'აირჩიე დასაშვები როლი.', 'integer' => 'გვერდი უნდა იყოს მთელი რიცხვი.', 'min' => 'გვერდი უნდა იყოს მინიმუმ :min.'];
    }
}
