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
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'as_of' => ['bail', 'required_with:date_from', 'nullable', 'date_format:Y-m-d',
                Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
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
            'as_of.required_with' => 'მიუთითე დასრულების თარიღი.',
            'as_of.after_or_equal' => 'დასრულების თარიღი დაწყების თარიღზე ადრე არ უნდა იყოს.',
        ];
    }

    public function attributes(): array
    {
        return [
            'q' => 'საძიებო ტექსტი', 'status' => 'სტატუსი', 'project_id' => 'პროექტი',
            'client_id' => 'კლიენტი', 'as_of' => 'დასრულების თარიღი', 'date_from' => 'დაწყების თარიღი',
            'import_status' => 'იმპორტის სტატუსი',
            'client_match' => 'კლიენტთან კავშირი',
            'sort' => 'დალაგების ველი', 'direction' => 'დალაგების მიმართულება', 'page' => 'გვერდის ნომერი',
        ];
    }
}
