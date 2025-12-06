<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParkingLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'address',
        'latitude',
        'longitude',
        'price_per_minute',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'price_per_minute' => 'decimal:2',

        ];
    }

    // Relationships
    public function spots()
    {
        return $this->hasMany(Spot::class);
    }

    public function availableSpots()
    {
        return $this->hasMany(Spot::class)->where('is_available', true);
    }

    public function savedByUsers()
    {
        return $this->belongsToMany(User::class, 'saved_parking_lots')->withTimestamps();
    }

    public function availableSpotsCount()
    {
        return $this->spots()->where('status', 'available')->count();
    }

    /**
     * إجمالي المواقف
     */
    public function totalSpotsCount()
    {
        return $this->spots()->count();
    }
}
