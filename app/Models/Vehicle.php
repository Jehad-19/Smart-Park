<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ← أضف هذا


class Vehicle extends Model
{
    use HasFactory , SoftDeletes; // ← أضف SoftDeletes هنا

    protected $fillable = [
        'user_id',
        'plate_number',
        'brand',
        'model',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
