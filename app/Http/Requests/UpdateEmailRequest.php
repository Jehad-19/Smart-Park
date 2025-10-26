<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_email' => 'required|email|unique:users,email',
        ];
    }

    public function messages(): array
    {
        return [
            'new_email.required' => 'البريد الإلكتروني الجديد مطلوب.',
            'new_email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'new_email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل.',
        ];
    }
}
