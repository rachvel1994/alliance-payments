<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'lowercase', 'max:255', Rule::unique(User::class)->ignore($this->route('user'))],
            'role' => ['present', 'nullable', 'string', Rule::in(array_keys(config('access.roles')))],
            'permissions' => ['present', 'array', 'max:'.(count(config('access.permissions')) - 1)],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(array_diff(array_keys(config('access.permissions')), ['users.manage']))],
            'password' => $this->isMethod('POST') ? ['required', 'string', Password::defaults(), 'confirmed'] : ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'ველი „:attribute“ აუცილებელია.',
            'present' => 'მიუთითე :attribute.',
            'string' => ':attribute უნდა იყოს ტექსტი.',
            'array' => ':attribute უნდა იყოს სია.',
            'max' => ':attribute არ უნდა აღემატებოდეს :max-ს.',
            'min' => 'პაროლი უნდა შეიცავდეს მინიმუმ :min სიმბოლოს.',
            'email.email' => 'მიუთითე სწორი ელ. ფოსტა.',
            'email.lowercase' => 'ელ. ფოსტაში გამოიყენე პატარა ასოები.',
            'email.unique' => 'ამ ელ. ფოსტით მომხმარებელი უკვე არსებობს.',
            'in' => 'არჩეული :attribute დაუშვებელია.',
            'distinct' => 'უფლება სიაში მეორდება.',
            'password.confirmed' => 'პაროლის დადასტურება არ ემთხვევა.',
            'password.prohibited' => 'არსებული ანგარიშის პაროლი პროფილიდან იცვლება.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'სახელი', 'email' => 'ელ. ფოსტა', 'role' => 'როლი', 'permissions' => 'უფლებები',
            'permissions.*' => 'უფლება', 'password' => 'პაროლი'];
    }
}
