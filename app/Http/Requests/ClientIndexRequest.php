<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ClientIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['name', 'id'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.string' => 'საძიებო ტექსტი უნდა იყოს სტრიქონი.',
            'q.max' => 'საძიებო ტექსტი არ უნდა აღემატებოდეს 100 სიმბოლოს.',
            'sort.in' => 'აირჩიე დალაგების ველი სიიდან.',
            'direction.in' => 'აირჩიე დალაგების მიმართულება სიიდან.',
            'page.integer' => 'გვერდის ნომერი უნდა იყოს მთელი რიცხვი.',
            'page.min' => 'გვერდის ნომერი უნდა იყოს მინიმუმ 1.',
        ];
    }
}
