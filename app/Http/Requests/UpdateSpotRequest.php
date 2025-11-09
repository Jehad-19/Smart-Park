<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $parkingLotId = $this->route('parkingLotId');
        $spotId = $this->route('spotId');

        return [
            'spot_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('spots')->where(function ($query) use ($parkingLotId) {
                    return $query->where('parking_lot_id', $parkingLotId);
                })->ignore($spotId),
            ],
            'type' => 'nullable|in:regular,disabled',
            'status' => 'nullable|in:available,occupied,reserved',
        ];
    }

    public function messages(): array
    {
        return [
            'spot_number.required' => 'رقم الموقف الفرعي مطلوب.',
            'spot_number.unique' => 'رقم الموقف الفرعي موجود مسبقاً في هذا الموقف.',
            'type.in' => 'نوع الموقف يجب أن يكون: regular أو disabled.',
            'status.in' => 'حالة الموقف يجب أن تكون: available، occupied، أو reserved.',
        ];
    }
}
