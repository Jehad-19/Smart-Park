<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $expiredBookings = \App\Models\Booking::where('status', 'pending')
            ->where('start_time', '<', now()->subMinutes(15))
            ->get();

        foreach ($expiredBookings as $booking) {
            $booking->update(['status' => 'expired']);
            $booking->spot->update(['status' => 'available']);
        }
    }
}
