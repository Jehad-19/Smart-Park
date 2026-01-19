<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens , SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
        'latitude',
        'longitude',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    // Relationships
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function savedParkingLots()
    {
        return $this->belongsToMany(ParkingLot::class, 'saved_parking_lots')->withTimestamps();
    }

    public function savedParkingLotRecords()
    {
        return $this->hasMany(SavedParkingLot::class);
    }

    public function hasSavedParkingLot(int $parkingLotId): bool
    {
        if ($this->relationLoaded('savedParkingLots')) {
            return $this->savedParkingLots->contains('id', $parkingLotId);
        }

        return $this->savedParkingLots()
            ->where('parking_lot_id', $parkingLotId)
            ->exists();
    }

    // Accessors & Mutators
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function setIsActiveAttribute($value)
    {
        $this->status = $value ? 'active' : 'inactive';
    }
}
