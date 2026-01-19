<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ← أضف هذا


class Spot extends Model
{
    use HasFactory , SoftDeletes; // ← أضف SoftDeletes هنا

    protected $fillable = [
        'parking_lot_id',
        'spot_number',
        'type',
        'status',
    ];

   

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
