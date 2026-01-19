<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreSpotRequest;
use App\Http\Requests\UpdateSpotRequest;
use App\Http\Requests\BulkCreateSpotsRequest;
use App\Http\Resources\SpotResource;
use App\Models\Spot;
use App\Models\ParkingLot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpotController extends BaseApiController
{
    /**
     * عرض جميع المواقف الفرعية لموقف معين
     */
    public function index(Request $request, $parkingLotId)
    {
        try {
            $parkingLot = ParkingLot::find($parkingLotId);

            if (!$parkingLot) {
                return $this->sendError('الموقف الرئيسي غير موجود.', [], 404);
            }

            $perPage = $request->input('per_page', 50);
            $status = $request->input('status');
            $type = $request->input('type');

            $query = $parkingLot->spots()->latest();

            if ($status) {
                $query->where('status', $status);
            }

            if ($type) {
                $query->where('type', $type);
            }

            $spots = $query->paginate($perPage);

            return $this->sendSuccess([
                'parking_lot' => [
                    'id' => $parkingLot->id,
                    'name' => $parkingLot->name,
                ],
                'spots' => SpotResource::collection($spots),
                'pagination' => [
                    'total' => $spots->total(),
                    'per_page' => $spots->perPage(),
                    'current_page' => $spots->currentPage(),
                    'last_page' => $spots->lastPage(),
                ]
            ], 'تم جلب المواقف الفرعية بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Get Spots Error');
        }
    }

    /**
     * إضافة موقف فرعي واحد
     */
    public function store(StoreSpotRequest $request, $parkingLotId)
    {
        try {
            $parkingLot = ParkingLot::find($parkingLotId);

            if (!$parkingLot) {
                return $this->sendError('الموقف الرئيسي غير موجود.', [], 404);
            }


            $validated = $request->validated();
            $adminId = $request->user()->id ?? null;

            $spot = null;
            DB::transaction(function () use ($parkingLot, $validated, &$spot, $adminId) {
                $spot = Spot::create([
                    'parking_lot_id' => $parkingLot->id,
                    'spot_number' => $validated['spot_number'],
                    'type' => $validated['type'] ?? 'regular',
                    'status' => $validated['status'] ?? 'available',
                    'admin_id' => $adminId,
                ]);
            });

            return $this->sendSuccess(
                ['spot' => new SpotResource($spot)],
                'تم إضافة الموقف الفرعي بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Store Spot Error');
        }
    }

    /**
     * إضافة عدة مواقف فرعية دفعة واحدة (Bulk Create)
     */
    public function bulkStore(BulkCreateSpotsRequest $request, $parkingLotId)
    {
        try {
            $parkingLot = ParkingLot::find($parkingLotId);

            if (!$parkingLot) {
                return $this->sendError('الموقف الرئيسي غير موجود.', [], 404);
            }

            $validated = $request->validated();
            $prefix = $validated['prefix'];
            $count = $validated['count'];
            $type = $validated['type'] ?? 'regular';

            $spots = [];
            $adminId = $request->user()->id ?? null;

            DB::transaction(function () use ($parkingLot, $prefix, $count, $type, &$spots, $adminId) {
                for ($i = 1; $i <= $count; $i++) {
                    $spotNumber = $prefix . $i;

                    $exists = Spot::where('parking_lot_id', $parkingLot->id)
                        ->where('spot_number', $spotNumber)
                        ->exists();

                    if (!$exists) {
                        $spots[] = Spot::create([
                            'parking_lot_id' => $parkingLot->id,
                            'spot_number' => $spotNumber,
                            'type' => $type,
                            'status' => 'available',
                            'admin_id' => $adminId,
                        ]);
                    }
                }
            });

            return $this->sendSuccess([
                'spots' => SpotResource::collection($spots),
                'created_count' => count($spots),
            ], 'تم إضافة المواقف الفرعية بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Bulk Store Spots Error');
        }
    }

    /**
     * عرض تفاصيل موقف فرعي محدد
     */
    public function show($parkingLotId, $spotId)
    {
        try {
            $spot = Spot::where('parking_lot_id', $parkingLotId)->find($spotId);

            if (!$spot) {
                return $this->sendError('الموقف الفرعي غير موجود.', [], 404);
            }

            return $this->sendSuccess(
                ['spot' => new SpotResource($spot)],
                'تم جلب بيانات الموقف الفرعي بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Show Spot Error');
        }
    }

    /**
     * تحديث موقف فرعي
     */
    public function update(UpdateSpotRequest $request, $parkingLotId, $spotId)
    {
        try {
            $spot = Spot::where('parking_lot_id', $parkingLotId)->find($spotId);

            if (!$spot) {
                return $this->sendError('الموقف الفرعي غير موجود.', [], 404);
            }

            $validated = $request->validated();
            $validated['admin_id'] = $request->user()->id ?? null;

            // ✅ التحقق: لا يمكن تحديث موقف مرتبط بحجز نشط
            $activeBooking = $spot->bookings()
                ->whereIn('status', ['pending', 'in_progress'])
                ->exists();

            if ($activeBooking) {
                return $this->sendError(
                    'لا يمكن تحديث الموقف لأنه مرتبط بحجز نشط.',
                    [],
                    400
                );
            }

            DB::transaction(function () use ($spot, $validated) {
                $spot->update($validated);
            });

            return $this->sendSuccess(
                ['spot' => new SpotResource($spot->fresh())],
                'تم تحديث الموقف الفرعي بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Update Spot Error');
        }
    }

    /**
     * حذف موقف فرعي
     */
    public function destroy($parkingLotId, $spotId)
    {
        try {
            $spot = Spot::where('parking_lot_id', $parkingLotId)->find($spotId);

            if (!$spot) {
                return $this->sendError('الموقف الفرعي غير موجود.', [], 404);
            }

            // ✅ التحقق: لا يمكن حذف موقف مرتبط بحجوزات نشطة
            $activeBookings = $spot->bookings()
                ->whereIn('status', ['pending', 'in_progress'])
                ->exists();

            if ($activeBookings) {
                return $this->sendError(
                    'لا يمكن حذف الموقف لأنه مرتبط بحجوزات نشطة.',
                    [],
                    400
                );
            }

            // ✅ التحقق: لا يمكن حذف موقف له حجوزات سابقة (اختياري - يمكن السماح)
            $hasAnyBookings = $spot->bookings()->exists();

            if ($hasAnyBookings) {
                return $this->sendError(
                    'لا يمكن حذف الموقف لأنه مرتبط بحجوزات سابقة. يمكنك تعطيله بدلاً من الحذف.',
                    [],
                    400
                );
            }

            $adminId = request()->user()->id ?? null;

            DB::transaction(function () use ($spot, $adminId) {
                $spot->admin_id = $adminId;
                $spot->save();
                $spot->delete();
            });

            return $this->sendSuccess([], 'تم حذف الموقف الفرعي بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Delete Spot Error');
        }
    }

    /**
     * تغيير حالة موقف فرعي
     */
    public function updateStatus(Request $request, $parkingLotId, $spotId)
    {
        try {
            $request->validate([
                'status' => 'required|in:available,occupied,reserved',
            ]);

            $spot = Spot::where('parking_lot_id', $parkingLotId)->find($spotId);

            if (!$spot) {
                return $this->sendError('الموقف الفرعي غير موجود.', [], 404);
            }

            // ✅ التحقق: لا يمكن تغيير حالة موقف محجوز/مشغول إلى available إذا كان له حجز نشط
            $newStatus = $request->status;

            if ($newStatus === 'available') {
                $activeBooking = $spot->bookings()
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->exists();

                if ($activeBooking) {
                    return $this->sendError(
                        'لا يمكن تغيير حالة الموقف إلى متاح لأنه مرتبط بحجز نشط.',
                        [],
                        400
                    );
                }
            }

            $adminId = $request->user()->id ?? null;

            DB::transaction(function () use ($spot, $newStatus, $adminId) {
                $spot->status = $newStatus;
                $spot->admin_id = $adminId;
                $spot->save();
            });

            return $this->sendSuccess(
                ['spot' => new SpotResource($spot)],
                'تم تحديث حالة الموقف الفرعي بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Update Spot Status Error');
        }
    }
}
