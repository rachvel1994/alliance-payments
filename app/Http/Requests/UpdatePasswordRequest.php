<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'მიუთითე მიმდინარე პაროლი.',
            'current_password.current_password' => 'მიმდინარე პაროლი არასწორია.',
            'password.required' => 'მიუთითე ახალი პაროლი.',
            'string' => 'პაროლი უნდა იყოს ტექსტი.',
            'min' => 'პაროლი უნდა შეიცავდეს მინიმუმ :min სიმბოლოს.',
            'password.confirmed' => 'პაროლის დადასტურება არ ემთხვევა.',
        ];
    }
}
