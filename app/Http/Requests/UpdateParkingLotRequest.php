<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParkingLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TODO: إضافة التحقق من صلاحيات المشرف
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'price_per_hour' => 'required|numeric|min:0|max:999999.99',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الموقف مطلوب.',
            'address.required' => 'العنوان مطلوب.',
            'latitude.required' => 'خط العرض مطلوب.',
            'latitude.between' => 'خط العرض يجب أن يكون بين -90 و 90.',
            'longitude.required' => 'خط الطول مطلوب.',
            'longitude.between' => 'خط الطول يجب أن يكون بين -180 و 180.',
            'price_per_hour.required' => 'السعر بالساعة مطلوب.',
            'price_per_hour.min' => 'السعر يجب أن يكون أكبر من أو يساوي 0.',
        ];
    }
}
