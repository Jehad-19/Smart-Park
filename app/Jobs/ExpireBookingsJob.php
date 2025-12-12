<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

use App\Models\Transaction;

class ExpireBookingsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $expiredBookings = \App\Models\Booking::with(['spot', 'user.wallet'])
            ->where('status', 'pending')
            ->where('start_time', '<=', now()->subMinutes(30))
            ->get();

        foreach ($expiredBookings as $booking) {
            DB::transaction(function () use ($booking) {
                $wallet = $booking->user?->wallet;
                $amount = $booking->total_price ?? 0;

                if ($wallet && $amount > 0) {
                    $balanceBefore = $wallet->balance;
                    $wallet->balance = $balanceBefore + $amount;
                    $wallet->save();

                    Transaction::create([
                        'wallet_id' => $wallet->id,
                        'booking_id' => $booking->id,
                        'type' => 'refund',
                        'amount' => $amount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $wallet->balance,
                        'description' => "استرجاع لحجز لم يتم تفعيله #{$booking->id}",
                    ]);
                }

                $booking->update(['status' => 'canceled']);
                $booking->spot?->update(['status' => 'available']);
            });
        }
    }
}
