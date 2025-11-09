<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkCreateSpotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prefix' => 'required|string|max:10',
            'count' => 'required|integer|min:1|max:100',
            'type' => 'nullable|in:regular,disabled',
        ];
    }

    public function messages(): array
    {
        return [
            'prefix.required' => 'البادئة (Prefix) مطلوبة.',
            'count.required' => 'عدد المواقف مطلوب.',
            'count.min' => 'يجب إضافة موقف واحد على الأقل.',
            'count.max' => 'الحد الأقصى 100 موقف في المرة الواحدة.',
            'type.in' => 'نوع الموقف يجب أن يكون: regular أو disabled.',
        ];
    }
}
