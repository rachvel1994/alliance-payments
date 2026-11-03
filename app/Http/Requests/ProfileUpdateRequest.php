<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'ველი „:attribute“ აუცილებელია.',
            'string' => ':attribute უნდა იყოს ტექსტი.',
            'max' => ':attribute არ უნდა აღემატებოდეს :max სიმბოლოს.',
            'email.email' => 'მიუთითე სწორი ელ. ფოსტის მისამართი.',
            'email.lowercase' => 'ელ. ფოსტა უნდა შეიცავდეს მხოლოდ პატარა ასოებს.',
            'email.unique' => 'ამ ელ. ფოსტით ანგარიში უკვე არსებობს.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'სახელი', 'email' => 'ელ. ფოსტა'];
    }
}
