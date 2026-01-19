<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ← أضف هذا


class SavedParkingLot extends Model
{
    use HasFactory , SoftDeletes; // ← أضف SoftDeletes هنا

    protected $fillable = [
        'user_id',
        'parking_lot_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parkingLot()
    {
        return $this->belongsTo(ParkingLot::class);
    }
}
