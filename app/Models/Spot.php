<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ← أضف هذا


class Spot extends Model
{
    use HasFactory, SoftDeletes; // ← أضف SoftDeletes هنا

    protected $fillable = [
        'parking_lot_id',
        'spot_number',
        'type',
        'status',
        'admin_id',
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
