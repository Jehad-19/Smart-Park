<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ← أضف هذا


class Booking extends Model
{
    use HasFactory, SoftDeletes; // ← أضف SoftDeletes هنا

    protected $fillable = [
        'user_id',
        'spot_id',
        'vehicle_id',
        'start_time',
        'end_time',
        'actual_start_time',
        'actual_end_time',
        'duration_minutes',
        'total_price',
        'status',
        'qr_code_token',
        'state',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'actual_start_time' => 'datetime',
        'actual_end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'total_price' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function spot()
    {
        return $this->belongsTo(Spot::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
