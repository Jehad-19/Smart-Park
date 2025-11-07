<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreParkingLotRequest;
use App\Http\Requests\UpdateParkingLotRequest;
use App\Http\Resources\ParkingLotResource;
use App\Helpers\LocationHelper;
use App\Models\ParkingLot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParkingLotController extends BaseApiController
{
    // ==================== User APIs ====================

    /**
     * جلب أقرب المواقف للمستخدم
     */
    public function getNearbyParkingLots(Request $request)
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius' => 'nullable|numeric|min:1|max:50',
            ]);

            $userLat = $request->latitude;
            $userLon = $request->longitude;
            $radius = $request->radius ?? 10; // افتراضياً 10 كم

            $parkingLots = ParkingLot::where('status', 'active')->get();

            $nearbyLots = $parkingLots->map(function ($lot) use ($userLat, $userLon) {
                $distance = LocationHelper::calculateDistance(
                    $userLat,
                    $userLon,
                    $lot->latitude,
                    $lot->longitude,
                    'km'
                );

                $lot->distance = $distance;
                $lot->available_spots = $lot->availableSpotsCount();
                $lot->total_spots = $lot->totalSpotsCount();
                return $lot;
            })
                ->filter(function ($lot) use ($radius) {
                    return $lot->distance <= $radius;
                })
                ->sortBy('distance')
                ->values();

            return $this->sendSuccess([
                'parking_lots' => ParkingLotResource::collection($nearbyLots),
                'total' => $nearbyLots->count(),
            ], 'تم جلب المواقف القريبة بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Get Nearby Parking Lots Error');
        }
    }

    /**
     * عرض تفاصيل موقف محدد (للمستخدم)
     */
    public function show($id)
    {
        try {
            $parkingLot = ParkingLot::where('status', 'active')->find($id);

            if (!$parkingLot) {
                return $this->sendError('الموقف غير موجود أو غير نشط.', [], 404);
            }

            $parkingLot->available_spots = $parkingLot->availableSpotsCount();
            $parkingLot->total_spots = $parkingLot->totalSpotsCount();

            return $this->sendSuccess(
                ['parking_lot' => new ParkingLotResource($parkingLot)],
                'تم جلب بيانات الموقف بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Show Parking Lot Error');
        }
    }

    // ==================== Admin APIs ====================

    /**
     * عرض جميع المواقف (للمشرف)
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $status = $request->input('status'); // active or inactive

            $query = ParkingLot::query()->latest();

            if ($status) {
                $query->where('status', $status);
            }

            $parkingLots = $query->paginate($perPage);

            // إضافة معلومات المواقف المتاحة لكل موقف
            $parkingLots->getCollection()->transform(function ($lot) {
                $lot->available_spots = $lot->availableSpotsCount();
                $lot->total_spots = $lot->totalSpotsCount();
                return $lot;
            });

            return $this->sendSuccess([
                'parking_lots' => ParkingLotResource::collection($parkingLots),
                'pagination' => [
                    'total' => $parkingLots->total(),
                    'per_page' => $parkingLots->perPage(),
                    'current_page' => $parkingLots->currentPage(),
                    'last_page' => $parkingLots->lastPage(),
                ]
            ], 'تم جلب المواقف بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Get Parking Lots Error');
        }
    }

    /**
     * إضافة موقف جديد (للمشرف)
     */
    public function store(StoreParkingLotRequest $request)
    {
        try {
            $validated = $request->validated();

            $parkingLot = null;
            DB::transaction(function () use ($validated, &$parkingLot) {
                $parkingLot = ParkingLot::create($validated);
            });

            return $this->sendSuccess(
                ['parking_lot' => new ParkingLotResource($parkingLot)],
                'تم إضافة الموقف بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Store Parking Lot Error');
        }
    }

    /**
     * تحديث موقف (للمشرف)
     */
    public function update(UpdateParkingLotRequest $request, $id)
    {
        try {
            $parkingLot = ParkingLot::find($id);

            if (!$parkingLot) {
                return $this->sendError('الموقف غير موجود.', [], 404);
            }

            $validated = $request->validated();

            DB::transaction(function () use ($parkingLot, $validated) {
                $parkingLot->update($validated);
            });

            return $this->sendSuccess(
                ['parking_lot' => new ParkingLotResource($parkingLot->fresh())],
                'تم تحديث الموقف بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Update Parking Lot Error');
        }
    }

    /**
     * حذف موقف (للمشرف)
     */
    public function destroy($id)
    {
        try {
            $parkingLot = ParkingLot::find($id);

            if (!$parkingLot) {
                return $this->sendError('الموقف غير موجود.', [], 404);
            }

            // TODO: التحقق من عدم وجود حجوزات نشطة
            // سنضيفها لاحقاً عند بناء Bookings

            DB::transaction(function () use ($parkingLot) {
                $parkingLot->delete(); // سيحذف الـ Spots تلقائياً (cascade)
            });

            return $this->sendSuccess([], 'تم حذف الموقف بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Delete Parking Lot Error');
        }
    }

    /**
     * تفعيل/تعطيل موقف (للمشرف)
     */
    public function toggleStatus($id)
    {
        try {
            $parkingLot = ParkingLot::find($id);

            if (!$parkingLot) {
                return $this->sendError('الموقف غير موجود.', [], 404);
            }

            DB::transaction(function () use ($parkingLot) {
                $parkingLot->status = $parkingLot->status === 'active' ? 'inactive' : 'active';
                $parkingLot->save();
            });

            return $this->sendSuccess(
                ['parking_lot' => new ParkingLotResource($parkingLot)],
                'تم تغيير حالة الموقف بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Toggle Status Error');
        }
    }
}
