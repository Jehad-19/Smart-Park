<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ← أضف هذا


class Transaction extends Model
{
    use HasFactory , SoftDeletes; // ← أضف SoftDeletes هنا

    protected $fillable = [
        'wallet_id',
        'booking_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'status',
        'description',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    // Relationships
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
