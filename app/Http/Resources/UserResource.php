<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'fcm_token' => $this->fcm_token,
            'wallet_balance' => $this->wallet ? $this->wallet->balance : 0,
            'vehicles_count' => $this->vehicles()->count(),
            'active_bookings_count' => $this->bookings()->whereIn('status', ['pending', 'active'])->count(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
