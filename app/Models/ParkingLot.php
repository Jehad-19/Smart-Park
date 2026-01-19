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
        'price_per_hour',
        'status',
        'admin_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'price_per_hour' => 'decimal:2',

        ];
    }

    /**
     * Backward compatibility: if price_per_hour is null but legacy price_per_minute exists, derive it.
     */
    public function getPricePerHourAttribute($value): ?float
    {
        if ($value !== null) {
            return (float) $value;
        }

        $legacyPerMinute = $this->attributes['price_per_minute'] ?? null;

        if ($legacyPerMinute !== null) {
            return (float) $legacyPerMinute * 60;
        }

        return null;
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

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->admin_id) && auth()->check()) {
                $model->admin_id = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->admin_id = auth()->id();
            }
        });

        static::deleting(function ($model) {
            if (auth()->check()) {
                $model->admin_id = auth()->id();
                $model->save();
            }
        });
    }
}
