<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|string|email|exists:users,email',
            'password' => 'required|string',
            'fcm_token' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني مطلوب لتسجيل الدخول.',
            'email.email' => 'الرجاء إدخال بريد إلكتروني صالح.',
            'email.exists' => 'البريد الإلكتروني الذي أدخلته غير مسجل لدينا.',
            'password.required' => 'كلمة المرور مطلوبة لتسجيل الدخول.',
            'password.exists' => 'كلمة المرور التي أدخلتها غير صحيحة.',
        ];
    }
}
