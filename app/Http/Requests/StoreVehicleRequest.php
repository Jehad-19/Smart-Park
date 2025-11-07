<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plate_number' => 'required|string|max:50|unique:vehicles,plate_number',
            'model' => 'required|string|max:255',
            'brand' => 'required|string|max:100'
        ];
    }

    public function messages(): array
    {
        return [
            'plate_number.required' => 'رقم اللوحة مطلوب.',
            'plate_number.unique' => 'رقم اللوحة هذا مُستخدم بالفعل في النظام.',
            'plate_number.max' => 'رقم اللوحة يجب أن لا يتجاوز 50 حرف.',
            'model.required' => 'نوع السيارة مطلوب.',
            'brand.required' => 'ماركة السيارة مطلوبة.',
            'model.max' => 'نوع السيارة يجب أن لا يتجاوز 255 حرف.',
        ];
    }
}
