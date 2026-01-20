<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'employee_number',
        'status',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function parkingLots()
    {
        return $this->hasMany(ParkingLot::class);
    }

    public function spots()
    {
        return $this->hasMany(Spot::class);
    }

    protected $casts = [
        'password' => 'hashed',
    ];

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function setIsActiveAttribute($value)
    {
        $this->status = $value ? 'active' : 'inactive';
    }
}
