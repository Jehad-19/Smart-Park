<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\BaseApiController;

use App\Models\Booking;
use App\Models\Spot;
use App\Models\Transaction;
use App\Models\Vehicle;
use App\Models\Wallet;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        // Allow booking only within the next 30 minutes (inclusive) and not in the past
        $startTime = Carbon::parse($request->start_time);
        $endTime = Carbon::parse($request->end_time);

        $now = now();
        $diffMinutes = $now->diffInMinutes($startTime, false); // negative if start is in the past

        // Diagnostics to trace time-window enforcement; remove once verified
        Log::info('booking_start_window', [
            'now' => $now->toDateTimeString(),
            'start_time' => $startTime->toDateTimeString(),
            'diff_minutes' => $diffMinutes,
            'user_id' => $user->id,
        ]);

        if ($diffMinutes < 0) {
            return $this->sendError('لا يمكن تحديد وقت بدء في الماضي', [], 422);
        }

        if ($diffMinutes > 30) {
            return $this->sendError('يجب أن يبدأ الحجز خلال 30 دقيقة من الآن كحد أقصى', [], 422);
        }

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
        // $minCost = ($spot->parkingLot->price_per_hour / 60) * 30;
        // if ($user->wallet->balance < $minCost) { ... }

        // Calculate total price based on duration
        $spot->load('parkingLot');
        $pricePerHour = $spot->parkingLot->price_per_hour ?? 0;
        if ($pricePerHour === 0 && isset($spot->parkingLot->price_per_minute)) {
            $pricePerHour = (float) $spot->parkingLot->price_per_minute * 60;
        }

        $durationMinutes = max(1, $startTime->diffInMinutes($endTime));
        $totalPrice = ($pricePerHour / 60) * $durationMinutes;

        $wallet = $user->wallet;
        if (!$wallet) {
            return $this->sendError('محفظة المستخدم غير متوفرة', [], 404);
        }

        if ($wallet->balance < $totalPrice) {
            return $this->sendError('الرصيد غير كافٍ لإتمام الحجز', [], 400);
        }

        DB::beginTransaction();
        try {
            $booking = new Booking([
                'user_id' => $user->id,
                'spot_id' => $request->spot_id,
                'vehicle_id' => $request->vehicle_id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'pending',
                'qr_code_token' => Str::uuid(),
                'total_price' => $totalPrice,
            ]);

            // Ensure state is persisted even if DB default is missing
            $booking->state = 0;
            $booking->save();

            // Deduct from wallet and record transaction
            $balanceBefore = $wallet->balance;
            $wallet->balance = $balanceBefore - $totalPrice;
            $wallet->save();

            Transaction::create([
                'wallet_id' => $wallet->id,
                'booking_id' => $booking->id,
                'type' => 'payment',
                'amount' => -$totalPrice,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'description' => "حجز موقف #{$booking->id}",
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

            $pricePerHour = $booking->spot->parkingLot->price_per_hour;
            if ($pricePerHour === null && isset($booking->spot->parkingLot->price_per_minute)) {
                $pricePerHour = (float) $booking->spot->parkingLot->price_per_minute * 60;
            }

            $pricePerMinute = $pricePerHour / 60;
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

    public function checkQr(string $token)
    {
        $booking = Booking::where('qr_code_token', $token)->first();

        if (! $booking) {
            return response()->json([
                'found' => false,
            ], 200);
        }

        return response()->json([
            'found' => true,
            'state' => (int) $booking->state,
        ], 200);
    }

    // POST /update-state  (UPDATES)
    public function updateState(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'state' => ['required', 'integer', 'in:0,1,2'],
        ]);

        $booking = Booking::where('qr_code_token', $data['token'])->first();

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'Token not found',
            ], 404);
        }

        // Optional: enforce transitions only (recommended)
        $current = (int) $booking->state;
        $next    = (int) $data['state'];
        if (!(($current === 0 && $next === 1) || ($current === 1 && $next === 2))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid state transition',
                'current' => $current,
            ], 422);
        }

        $booking->state = $next;
        $booking->save();

        return response()->json([
            'success' => true,
            'state'   => (int) $booking->state,
        ], 200);
    }
}
