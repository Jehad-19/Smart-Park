<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\BookingResource;
use App\Http\Resources\UserResource;
use App\Models\Booking;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    public function summary(Request $request)
    {
        try {
            $user = $request->user()->load(['wallet', 'vehicles']);
            $wallet = $user->wallet;

            $activeBooking = Booking::with(['spot.parkingLot', 'vehicle'])
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->orderByDesc('actual_start_time')
                ->first();

            $upcomingBooking = Booking::with(['spot.parkingLot', 'vehicle'])
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->where('start_time', '>=', now()->subHour())
                ->orderBy('start_time')
                ->first();

            $completedCount = Booking::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count();

            $activeCount = Booking::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'active'])
                ->count();

            $totalBookings = Booking::where('user_id', $user->id)->count();
            $totalSpent = Booking::where('user_id', $user->id)
                ->where('status', 'completed')
                ->sum('total_price');

            $recentTransactions = $wallet
                ? $wallet->transactions()->latest()->take(5)->get()->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => $transaction->type,
                        'amount' => (float) $transaction->amount,
                        'description' => $transaction->description,
                        'created_at' => $transaction->created_at,
                    ];
                })->values()->toArray()
                : [];

            $alerts = [];
            if ($user->vehicles->count() === 0) {
                $alerts[] = 'أضف مركبتك الأولى لتسهيل عملية الحجز.';
            }
            if (!$wallet || $wallet->balance < 25) {
                $alerts[] = 'رصيد محفظتك منخفض، قم بإعادة الشحن لتفادي تعطل الحجز.';
            }
            if (!$activeBooking && !$upcomingBooking) {
                $alerts[] = 'لا توجد حجوزات حالياً، ابحث عن موقف قريب واحجزه الآن.';
            }

            return $this->sendSuccess([
                'user' => new UserResource($user),
                'wallet' => [
                    'balance' => $wallet ? (float) $wallet->balance : 0.0,
                    'currency' => $wallet->currency ?? 'SAR',
                    'status' => $wallet->status ?? 'active',
                ],
                'active_booking' => $activeBooking ? new BookingResource($activeBooking) : null,
                'next_booking' => $upcomingBooking ? new BookingResource($upcomingBooking) : null,
                'stats' => [
                    'total_bookings' => $totalBookings,
                    'active_bookings' => $activeCount,
                    'completed_bookings' => $completedCount,
                    'vehicles_count' => $user->vehicles->count(),
                    'wallet_balance' => $wallet ? (float) $wallet->balance : 0.0,
                    'total_spent' => (float) $totalSpent,
                ],
                'recent_transactions' => $recentTransactions,
                'alerts' => $alerts,
            ], 'تم جلب لوحة التحكم بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Dashboard Summary Error');
        }
    }
}
