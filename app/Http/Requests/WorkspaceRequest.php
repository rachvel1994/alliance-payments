<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ImportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['free', 'sold'])],
            'import_status' => ['nullable', Rule::in(ImportStatus::cases())],
            'client_match' => ['nullable', Rule::in(['matched', 'unmatched'])],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', Rule::in(['id', 'date', 'price', 'amount'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'string' => ':attribute უნდა იყოს ტექსტი.',
            'max' => ':attribute არ უნდა აღემატებოდეს :max სიმბოლოს.',
            'integer' => ':attribute უნდა იყოს მთელი რიცხვი.',
            'min' => ':attribute უნდა იყოს მინიმუმ :min.',
            'exists' => 'შერჩეული :attribute ვერ მოიძებნა.',
            'in' => 'აირჩიე :attribute დასაშვები სიიდან.',
            'date_format' => ':attribute უნდა იყოს ფორმატში :format.',
        ];
    }

    public function attributes(): array
    {
        return [
            'q' => 'საძიებო ტექსტი', 'status' => 'სტატუსი', 'project_id' => 'პროექტი',
            'client_id' => 'კლიენტი', 'as_of' => 'საანგარიშო თარიღი',
            'import_status' => 'იმპორტის სტატუსი',
            'client_match' => 'კლიენტთან კავშირი',
            'sort' => 'დალაგების ველი', 'direction' => 'დალაგების მიმართულება', 'page' => 'გვერდის ნომერი',
        ];
    }
}
