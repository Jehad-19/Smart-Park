<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Spot extends Model
{
    use HasFactory;

    protected $fillable = [
        'parking_lot_id',
        'spot_number',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'enum',
        ];
    }

    // Relationships
    public function parkingLot()
    {
        return $this->belongsTo(ParkingLot::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
