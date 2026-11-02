<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class SaveClientRequest extends BusinessRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'personal_number' => ['required', 'string', 'regex:/\A\d{11}\z/', Rule::unique('clients')->ignore($this->route('client'))],
            'phone' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'კლიენტის სახელი', 'personal_number' => 'პირადი ნომერი', 'phone' => 'ტელეფონი'];
    }
}
