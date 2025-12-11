<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParkingLotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pricePerHour = $this->price_per_hour;
        if ($pricePerHour === null && isset($this->price_per_minute)) {
            $pricePerHour = (float) $this->price_per_minute * 60;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'price_per_hour' => $pricePerHour !== null ? (float) $pricePerHour : 0.0,
            'status' => $this->status,
            'distance' => isset($this->distance) ? (float) $this->distance : null,
            'available_spots' => $this->available_spots ?? 0,
            'total_spots' => $this->total_spots ?? 0,
            'is_saved' => (bool) ($this->is_saved ?? false),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
