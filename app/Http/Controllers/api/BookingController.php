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
use Illuminate\Support\Facades\Hash;

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

        // Allow booking at any future time (still disallow past)
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

        $hasActiveBooking = Booking::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'active'])
            ->exists();

        if ($hasActiveBooking) {
            return $this->sendError('لديك حجز نشط بالفعل. لا يمكنك إنشاء حجز جديد حتى اكتمال أو إلغاء الحجز الحالي.', [], 400);
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
        // Log booking attempt details for debugging (user will reproduce and send laravel.log)
        Log::info('booking_store_attempt', [
            'user_id' => $user->id,
            'spot_id' => $request->spot_id,
            'vehicle_id' => $request->vehicle_id,
            'start_time' => $startTime->toDateTimeString(),
            'end_time' => $endTime->toDateTimeString(),
            'duration_minutes' => $durationMinutes,
            'total_price' => $totalPrice,
            'wallet_balance' => $wallet->balance ?? null,
        ]);

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

            // Log that payment transaction was recorded
            Log::info('booking_payment_recorded', [
                'booking_id' => $booking->id,
                'wallet_id' => $wallet->id,
                'amount' => -$totalPrice,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
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
            Log::error('booking_store_failed', [
                'user_id' => $user->id ?? null,
                'spot_id' => $request->spot_id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'فشل إنشاء الحجز', 'error' => $e->getMessage()], 500);
        }
    }

    public function cancel($id)
    {
        $booking = Booking::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Preliminary log: record cancel call and booking snapshot
        Log::info('cancel_called', [
            'booking_id' => $booking->id,
            'booking_status' => $booking->status,
            'start_time_raw' => $booking->start_time,
            'user_id' => auth()->id(),
        ]);

        // Allow cancellation if current time is before the scheduled start time.
        // Use precise second-level comparison to avoid edge-case float rounding.
        $nowPre = Carbon::now();
        $startTimePre = Carbon::parse($booking->start_time);
        if ($nowPre->gt($startTimePre)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء الحجز بعد بدء الوقت'
            ], 400);
        }

        DB::beginTransaction();
        try {

            // Use application timezone and ensure start_time is a Carbon instance
            $startTime = Carbon::parse($booking->start_time);
            $now = Carbon::now();

            // حساب الفرق بالثواني والدقائق (دقة أعلى لتفادي حدود التقريب)
            $secondsDiff = $now->diffInSeconds($startTime, false);
            $minutesDiff = $secondsDiff / 60; // may be fractional

            Log::info('cancel_attempt', [
                'booking_id' => $booking->id,
                'user_id' => auth()->id(),
                'now' => $now->toDateTimeString(),
                'start_time' => $startTime->toDateTimeString(),
                'seconds_diff' => $secondsDiff,
                'minutes_diff' => $minutesDiff,
                'total_price' => $booking->total_price,
                'booking_started' => $secondsDiff < 0 ? 'YES' : 'NO',
            ]);

            $refunded = false;
            $refundAmount = 0;

            // ✅ استرجاع المبلغ إذا لم يبدأ الحجز بعد (الآن <= وقت البدء)
            if ($now->lte($startTime)) {
                $user = $booking->user;
                $wallet = $user->wallet;
                $refundAmount = (float) ($booking->total_price ?? 0);

                Log::info('refund_check', [
                    'wallet_present' => $wallet ? true : false,
                    'refund_amount' => $refundAmount,
                ]);

                if ($wallet && $refundAmount > 0) {
                    $balanceBefore = $wallet->balance;
                    $wallet->balance = $balanceBefore + $refundAmount;
                    $wallet->save();

                    Transaction::create([
                        'wallet_id' => $wallet->id,
                        'booking_id' => $booking->id,
                        'type' => 'refund',
                        'amount' => $refundAmount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $wallet->balance,
                        'description' => "استرداد مبلغ الحجز #{$booking->id}",
                    ]);

                    Log::info('refund_applied', [
                        'wallet_id' => $wallet->id,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $wallet->balance,
                        'refund_amount' => $refundAmount,
                        'transaction_type' => 'refund'
                    ]);

                    $refunded = true;
                } else {
                    Log::warning('refund_skipped', [
                        'booking_id' => $booking->id,
                        'reason' => !$wallet ? 'no_wallet' : 'zero_amount',
                    ]);
                }
            } else {
                // الحجز بدأ بالفعل - لا استرجاع
                Log::warning('refund_denied', [
                    'booking_id' => $booking->id,
                    'seconds_diff' => $secondsDiff,
                    'minutes_diff' => $minutesDiff,
                    'reason' => 'booking_already_started',
                ]);
            }

            $booking->update(['status' => 'canceled']);
            $booking->spot->update(['status' => 'available']);

            DB::commit();

            // ✅ الرسائل الصحيحة
            $message = 'تم إلغاء الحجز بنجاح';
            if ($refunded) {
                $message .= '، وتمت إعادة المبلغ إلى محفظتك';
            } else {
                $message .= '. لم يتم استرداد المبلغ لأن الحجز قد بدأ بالفعل';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'refunded' => $refunded,
                'refund_amount' => $refundAmount,
                'minutes_to_start' => max(0, $minutesDiff),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('cancel_failed', [
                'booking_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل إلغاء الحجز',
                'error' => $e->getMessage()
            ], 500);
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

            // Ensure we have an actual start; fallback to scheduled start
            if (!$startTime) {
                $startTime = $booking->start_time;
                $booking->actual_start_time = $startTime;
            }

            // Billing window: start from the earlier of (actual_start_time, scheduled start)
            // and end at the later of (actual_end_time, scheduled end).
            $billableStart = $startTime->copy()->min($booking->start_time);
            $billableEnd = $endTime->copy()->max($booking->end_time);

            // Total billable duration in minutes (includes early entry and overtime)
            $durationMinutes = max(1, $billableStart->diffInMinutes($billableEnd));

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

    public function checkQr($token)
    {
        $booking = Booking::where('qr_code_token', $token)->first();

        if (! $booking) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'state' => (int) $booking->state,
        ]);
    }

    // POST /update-state  (UPDATES)
    public function updateState(Request $request)
    {
        $booking = Booking::where('qr_code_token', $request->token)->first();

        if (! $booking) {
            return response()->json(['success' => false]);
        }

        $booking->state = $request->state;
        $booking->save();

        return response()->json(['success' => true]);
    }

    /**
     * Extend an active or pending booking by a given number of minutes.
     */
    public function extend(Request $request, $id)
    {
        $request->validate([
            'minutes' => 'required|integer|min:1|max:1440',
        ]);

        $user = $request->user();

        $booking = Booking::with(['spot.parkingLot'])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'active'])
            ->first();

        if (!$booking) {
            return $this->sendError('الحجز غير موجود أو غير قابل للتمديد', [], 404);
        }

        $extraMinutes = (int) $request->input('minutes');

        $currentEnd = Carbon::parse($booking->end_time);
        $newEnd = $currentEnd->copy()->addMinutes($extraMinutes);

        // Prevent overlap with other bookings on same spot
        $overlap = Booking::where('spot_id', $booking->spot_id)
            ->whereIn('status', ['pending', 'active'])
            ->where('id', '!=', $booking->id)
            ->where(function ($q) use ($currentEnd, $newEnd) {
                $q->whereBetween('start_time', [$currentEnd, $newEnd])
                    ->orWhereBetween('end_time', [$currentEnd, $newEnd])
                    ->orWhere(function ($qq) use ($currentEnd, $newEnd) {
                        $qq->where('start_time', '<', $currentEnd)
                            ->where('end_time', '>', $newEnd);
                    });
            })
            ->exists();

        if ($overlap) {
            return $this->sendError('الوقت المطلوب يتداخل مع حجز آخر لهذا الموقف', [], 400);
        }

        $pricePerHour = $booking->spot->parkingLot->price_per_hour;
        if ($pricePerHour === null && isset($booking->spot->parkingLot->price_per_minute)) {
            $pricePerHour = (float) $booking->spot->parkingLot->price_per_minute * 60;
        }

        $pricePerMinute = $pricePerHour / 60;
        $extraCost = $pricePerMinute * $extraMinutes;

        $wallet = $user->wallet;
        if (!$wallet) {
            return $this->sendError('محفظة المستخدم غير موجودة', [], 404);
        }

        if ($wallet->balance < $extraCost) {
            return $this->sendError('الرصيد غير كافٍ للتمديد', [], 400);
        }

        DB::beginTransaction();
        try {
            $balanceBefore = $wallet->balance;
            $wallet->balance -= $extraCost;
            $wallet->save();
            $balanceAfter = $wallet->balance;

            Transaction::create([
                'wallet_id' => $wallet->id,
                'booking_id' => $booking->id,
                'type' => 'payment',
                'amount' => -$extraCost,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => "تمديد حجز #{$booking->id}",
            ]);

            // ✅ التحديث الصحيح
            $booking->end_time = $newEnd;
            $booking->total_price = ($booking->total_price ?? 0) + $extraCost;
            $booking->save();

            // ✅ إعادة تحميل البيانات من Database
            $booking->refresh();

            DB::commit();

            // ✅ إرجاع البيانات بصيغة صحيحة
            return response()->json([
                'success' => true,
                'message' => 'تم تمديد الحجز بنجاح',
                'booking' => [
                    'id' => $booking->id,
                    'start_time' => $booking->start_time->toDateTimeString(),
                    'end_time' => $booking->end_time->toDateTimeString(), // ← ISO 8601 format
                    'total_price' => $booking->total_price,
                    'status' => $booking->status,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Extend booking error', [
                'booking_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('فشل تمديد الحجز', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update (edit) a pending booking. Handles vehicle change or time changes.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'vehicle_id' => 'sometimes|exists:vehicles,id',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
        ]);

        $user = $request->user();

        $booking = Booking::with(['spot.parkingLot'])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$booking) {
            return $this->sendError('الحجز غير موجود أو غير قابل للتعديل', [], 404);
        }

        $newStart = $request->has('start_time') ? Carbon::parse($request->input('start_time')) : Carbon::parse($booking->start_time);
        $newEnd = $request->has('end_time') ? Carbon::parse($request->input('end_time')) : Carbon::parse($booking->end_time);

        // Do not allow changing to past
        if (now()->gt($newStart)) {
            return $this->sendError('لا يمكن تحديد وقت بدء في الماضي', [], 422);
        }

        // Prevent overlap with other bookings on same spot
        $overlap = Booking::where('spot_id', $booking->spot_id)
            ->whereIn('status', ['pending', 'active'])
            ->where('id', '!=', $booking->id)
            ->where(function ($q) use ($newStart, $newEnd) {
                $q->whereBetween('start_time', [$newStart, $newEnd])
                    ->orWhereBetween('end_time', [$newStart, $newEnd])
                    ->orWhere(function ($qq) use ($newStart, $newEnd) {
                        $qq->where('start_time', '<', $newStart)
                            ->where('end_time', '>', $newEnd);
                    });
            })
            ->exists();

        if ($overlap) {
            return $this->sendError('الوقت المطلوب يتداخل مع حجز آخر لهذا الموقف', [], 400);
        }

        // Calculate price difference (if times changed)
        $spot = $booking->spot;
        $spot->load('parkingLot');
        $pricePerHour = $spot->parkingLot->price_per_hour ?? null;
        if ($pricePerHour === null && isset($spot->parkingLot->price_per_minute)) {
            $pricePerHour = (float) $spot->parkingLot->price_per_minute * 60;
        }

        $durationMinutesOld = max(1, Carbon::parse($booking->start_time)->diffInMinutes(Carbon::parse($booking->end_time)));
        $durationMinutesNew = max(1, $newStart->diffInMinutes($newEnd));

        $pricePerMinute = ($pricePerHour ?? 0) / 60;
        $oldTotal = ($booking->total_price ?? ($durationMinutesOld * $pricePerMinute));
        $newTotal = $durationMinutesNew * $pricePerMinute;
        $diff = $newTotal - $oldTotal;

        $wallet = $user->wallet;
        if ($diff > 0) {
            if (!$wallet) return $this->sendError('محفظة المستخدم غير موجودة', [], 404);
            if ($wallet->balance < $diff) {
                return $this->sendError('الرصيد غير كافٍ لتغطية تكلفة التعديل', [], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Apply vehicle change if provided
            if ($request->has('vehicle_id')) {
                $booking->vehicle_id = $request->input('vehicle_id');
            }

            // Apply time changes and handle payment/refund
            if ($request->has('start_time') || $request->has('end_time')) {
                // If extra cost, deduct; if negative, refund
                if ($diff > 0) {
                    $balanceBefore = $wallet->balance;
                    $wallet->balance -= $diff;
                    $wallet->save();
                    Transaction::create([
                        'wallet_id' => $wallet->id,
                        'booking_id' => $booking->id,
                        'type' => 'payment',
                        'amount' => -$diff,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $wallet->balance,
                        'description' => "تكلفة تعديل حجز #{$booking->id}",
                    ]);
                } elseif ($diff < 0) {
                    // refund the difference
                    $refund = (float) abs($diff);
                    if ($wallet && $refund > 0) {
                        $balanceBefore = $wallet->balance;
                        $wallet->balance += $refund;
                        $wallet->save();
                        Transaction::create([
                            'wallet_id' => $wallet->id,
                            'booking_id' => $booking->id,
                            'type' => 'refund',
                            'amount' => $refund,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $wallet->balance,
                            'description' => "استرداد مقابل تعديل حجز #{$booking->id}",
                        ]);
                    }
                }

                $booking->start_time = $newStart;
                $booking->end_time = $newEnd;
                $booking->total_price = $newTotal;
            }

            $booking->save();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الحجز بنجاح',
                'booking' => $booking,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('update_booking_failed', ['booking_id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->sendError('فشل تحديث الحجز', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a booking after validating user's password.
     * Only pending (not started) bookings can be deleted via this endpoint.
     */
    public function destroy(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->input('password'), $user->password)) {
            return $this->sendError('كلمة المرور غير صحيحة', [], 401);
        }

        $booking = Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Do not allow deleting active bookings
        $now = Carbon::now();
        $start = Carbon::parse($booking->start_time);
        if ($booking->status === 'active' || $now->gt($start)) {
            return $this->sendError('لا يمكن حذف الحجز بعد بدء الوقت. الرجاء إلغاء الحجز بدلاً من الحذف.', [], 400);
        }

        DB::beginTransaction();
        try {
            // If pending and not started, refund full amount
            $refunded = false;
            $refundAmount = 0;
            $wallet = $user->wallet;
            if ($booking->status === 'pending') {
                $refundAmount = (float) ($booking->total_price ?? 0);
                if ($wallet && $refundAmount > 0) {
                    $balanceBefore = $wallet->balance;
                    $wallet->balance = $balanceBefore + $refundAmount;
                    $wallet->save();

                    Transaction::create([
                        'wallet_id' => $wallet->id,
                        'booking_id' => $booking->id,
                        'type' => 'refund',
                        'amount' => $refundAmount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $wallet->balance,
                        'description' => "استرداد عند حذف الحجز #{$booking->id}",
                    ]);

                    $refunded = true;
                }
            }

            // Free the spot if necessary
            if ($booking->spot) {
                $booking->spot->update(['status' => 'available']);
            }

            $booking->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الحجز بنجاح',
                'refunded' => $refunded,
                'refund_amount' => $refundAmount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('delete_booking_failed', ['booking_id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->sendError('فشل حذف الحجز', ['error' => $e->getMessage()], 500);
        }
    }
}
