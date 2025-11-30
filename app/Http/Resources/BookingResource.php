<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_time' => $this->start_time, // Carbon instance, will be ISO string
            'end_time' => $this->end_time,
            'status' => $this->status,
            'total_price' => $this->total_price,
            'qr_code' => $this->qr_code_token,
            'created_at' => $this->created_at,
            'parking_lot' => [
                'name' => $this->spot->parkingLot->name,
                'address' => $this->spot->parkingLot->address,
                'image' => $this->spot->parkingLot->image,
            ],
            'spot' => [
                'id' => $this->spot->id,
                'spot_number' => $this->spot->spot_number,
            ],
            'vehicle' => [
                'id' => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'brand' => $this->vehicle->brand,
                'model' => $this->vehicle->model,
            ],
        ];
    }
}
