<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\BaseApiController;

use App\Models\Booking;
use App\Models\Spot;
use App\Models\Transaction;
use App\Models\Vehicle;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\ScanBookingRequest;
use App\Http\Resources\BookingResource;

class BookingController extends BaseApiController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');

        $query = Booking::where('user_id', $user->id)
            ->with(['spot.parkingLot', 'vehicle']) // Eager load relationships
            ->orderBy('created_at', 'desc');

        if ($status) {
            // Support multiple statuses comma separated if needed, or single
            // For now, let's assume single status or 'active' which might mean pending+active
            if ($status === 'active_tab') {
                $query->whereIn('status', ['pending', 'active']);
            } elseif ($status === 'completed_tab') {
                $query->where('status', 'completed');
            } elseif ($status === 'cancelled_tab') {
                $query->where('status', 'canceled'); // Note: database enum is 'canceled' (one l) usually, check migration if unsure. Controller uses 'canceled' in cancel method.
            } else {
                $query->where('status', $status);
            }
        }

        $bookings = $query->get();

        $data = BookingResource::collection($bookings)->toArray($request);
        return $this->sendSuccess($data, 'Bookings retrieved successfully');
    }

    public function store(StoreBookingRequest $request)
    {
        // Validation handled by Form Request

        $user = $request->user();

        // Check if spot is available
        $spot = Spot::where('id', $request->spot_id)->where('status', 'available')->first();
        if (!$spot) {
            return $this->sendError('الموقف غير متاح', [], 400);
        }

        // Check overlapping bookings
        $exists = Booking::where('spot_id', $request->spot_id)
            ->whereIn('status', ['pending', 'active'])
            ->where(function ($query) use ($request) {
                $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                    ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('start_time', '<', $request->start_time)
                            ->where('end_time', '>', $request->end_time);
                    });
            })
            ->exists();

        if ($exists) {
            return $this->sendError('الموقف محجوز بالفعل في هذا الوقت', [], 400);
        }

        // Optional: Check minimum balance (e.g., cost of 30 mins)
        // $minCost = $spot->parkingLot->price_per_minute * 30;
        // if ($user->wallet->balance < $minCost) { ... }

        DB::beginTransaction();
        try {
            $booking = Booking::create([
                'user_id' => $user->id,
                'spot_id' => $request->spot_id,
                'vehicle_id' => $request->vehicle_id,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'status' => 'pending',
                'qr_code_token' => Str::uuid(),
            ]);

            $spot->update(['status' => 'reserved']);

            DB::commit();

            return response()->json([
                'message' => 'تم إنشاء الحجز بنجاح',
                'booking' => $booking,
                'qr_code' => $booking->qr_code_token, // In real app, generate QR image or send string
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'فشل إنشاء الحجز', 'error' => $e->getMessage()], 500);
        }
    }

    public function cancel($id)
    {
        $booking = Booking::where('id', $id)->where('user_id', auth()->id())->firstOrFail();

        if ($booking->status !== 'pending') {
            return response()->json(['message' => 'لا يمكن إلغاء حجز غير معلق'], 400);
        }

        DB::beginTransaction();
        try {
            $booking->update(['status' => 'canceled']);
            $booking->spot->update(['status' => 'available']);
            DB::commit();
            return response()->json(['message' => 'تم إلغاء الحجز بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'فشل إلغاء الحجز'], 500);
        }
    }

    public function scanEntrance(ScanBookingRequest $request)
    {
        // Validation handled by Form Request

        $booking = Booking::where('qr_code_token', $request->qr_code)->first();

        if (!$booking) {
            return response()->json(['message' => 'رمز QR غير صالح'], 404);
        }

        if ($booking->status !== 'pending') {
            return response()->json(['message' => 'الحجز ليس في حالة انتظار'], 400);
        }

        // Allow entry within a window (e.g., 15 mins before start time)
        // if (now()->diffInMinutes($booking->start_time, false) > 15) { ... }

        DB::beginTransaction();
        try {
            $booking->update([
                'status' => 'active',
                'actual_start_time' => now(),
            ]);
            $booking->spot->update(['status' => 'occupied']);
            DB::commit();
            return response()->json(['message' => 'أهلاً بك! تم تأكيد الدخول.', 'booking' => $booking]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'خطأ في معالجة الدخول'], 500);
        }
    }

    public function scanExit(ScanBookingRequest $request)
    {
        // Validation handled by Form Request

        $booking = Booking::where('qr_code_token', $request->qr_code)->first();

        if (!$booking) {
            return response()->json(['message' => 'رمز QR غير صالح'], 404);
        }

        if ($booking->status !== 'active') {
            return response()->json(['message' => 'الحجز ليس نشطاً'], 400);
        }

        DB::beginTransaction();
        try {
            $endTime = now();
            $startTime = $booking->actual_start_time;

            // Calculate actual duration in minutes
            $actualDuration = $startTime->diffInMinutes($endTime);
            if ($actualDuration < 1) $actualDuration = 1;

            // Calculate booked duration in minutes
            $bookedDuration = $booking->start_time->diffInMinutes($booking->end_time);

            // Charge for the greater of the two (Booked vs Actual)
            $durationMinutes = max($actualDuration, $bookedDuration);

            $pricePerMinute = $booking->spot->parkingLot->price_per_minute;
            $totalPrice = $durationMinutes * $pricePerMinute;

            $user = $booking->user;

            // Deduct from wallet
            // Assuming user has a wallet relation
            $wallet = $user->wallet;
            if (!$wallet) {
                // Handle no wallet case, maybe create one or error
                // For now assuming wallet exists
                return $this->sendError('محفظة المستخدم غير موجودة', [], 404);
            }

            $balanceBefore = $wallet->balance;
            $wallet->balance -= $totalPrice;
            $wallet->save();
            $balanceAfter = $wallet->balance;

            // Record transaction
            Transaction::create([
                'wallet_id' => $wallet->id,
                'amount' => -$totalPrice,
                'type' => 'payment', // or 'withdrawal'
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => "رسوم موقف للحجز #{$booking->id}",
                'booking_id' => $booking->id,
            ]);

            $booking->update([
                'status' => 'completed',
                'actual_end_time' => $endTime,
                'duration_minutes' => $durationMinutes,
                'total_price' => $totalPrice,
            ]);

            $booking->spot->update(['status' => 'available']);

            DB::commit();

            return response()->json([
                'message' => 'وداعاً! تم تأكيد الخروج.',
                'booking' => $booking,
                'cost' => $totalPrice,
                'duration' => $durationMinutes . ' دقيقة'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'خطأ في معالجة الخروج', 'error' => $e->getMessage()], 500);
        }
    }
}
