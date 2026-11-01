<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StorePaymentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:5120']];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'აირჩიე Excel ფაილი.',
            'file.file' => 'ატვირთე ფაილი.',
            'file.mimes' => 'დასაშვებია მხოლოდ XLSX ფორმატის ფაილი.',
            'file.extensions' => 'ფაილის გაფართოება უნდა იყოს .xlsx.',
            'file.max' => 'ფაილის ზომა არ უნდა აღემატებოდეს 5 MiB-ს.',
            'file.uploaded' => 'ფაილის ატვირთვა ვერ მოხერხდა. სცადე ხელახლა.',
        ];
    }
}
