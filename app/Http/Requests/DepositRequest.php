<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1|max:10000',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'مبلغ الشحن مطلوب.',
            'amount.numeric' => 'المبلغ يجب أن يكون رقماً.',
            'amount.min' => 'الحد الأدنى للشحن هو 1 دينار .',
            'amount.max' => 'الحد الأقصى للشحن هو 10000 دينار.',
        ];
    }
}
