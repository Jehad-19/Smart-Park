<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            // رسائل حقل الاسم
            'name.required' => 'حقل الاسم مطلوب.',

            // رسائل حقل البريد الإلكتروني
            'email.required' => 'حقل البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني يجب أن يكون بتنسيق صحيح (مثال: user@example.com).',
            'email.unique' => 'عذراً، هذا البريد الإلكتروني مسجل لدينا بالفعل.',

            // رسائل حقل رقم الهاتف
            'phone.required' => 'حقل رقم الهاتف مطلوب.',
            'phone.unique' => 'عذراً، رقم الهاتف هذا مسجل لدينا بالفعل.',

            // رسائل حقل كلمة المرور
            'password.required' => 'حقل كلمة المرور مطلوب.',
            'password.min' => 'كلمة المرور يجب أن لا تقل عن 8 أحرف.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
