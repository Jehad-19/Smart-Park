<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParkingLot extends Model
{
    use HasFactory, SoftDeletes;

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
            'status' => 'enum',
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
}
