<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'كلمة المرور الحالية مطلوبة.',
            'new_password.required' => 'كلمة المرور الجديدة مطلوبة.',
            'new_password.min' => 'كلمة المرور الجديدة يجب أن لا تقل عن 8 أحرف.',
            'new_password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
