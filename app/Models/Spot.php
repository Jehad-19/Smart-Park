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
            if (empty($model->admin_id)) {
                $model->admin_id = self::resolveAdminId();
            }
        });

        static::updating(function ($model) {
            $adminId = self::resolveAdminId();
            if ($adminId !== null) {
                $model->admin_id = $adminId;
            }
        });

        static::deleting(function ($model) {
            $adminId = self::resolveAdminId();
            if ($adminId !== null) {
                $model->admin_id = $adminId;
                $model->save();
            }
        });
    }

    protected static function resolveAdminId(): ?int
    {
        if (auth()->guard('admin')->check()) {
            return auth()->guard('admin')->id();
        }

        if (auth()->check() && auth()->user() instanceof \App\Models\Admin) {
            return auth()->id();
        }

        return null;
    }
}
