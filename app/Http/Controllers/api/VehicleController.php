<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleController extends BaseApiController
{
    /**
     * عرض جميع سيارات المستخدم
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $vehicles = $user->vehicles()->latest()->get();

            return $this->sendSuccess(
                ['vehicles' => VehicleResource::collection($vehicles)],
                'تم جلب السيارات بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Get Vehicles Error');
        }
    }

    /**
     * إضافة سيارة جديدة
     */
    public function store(StoreVehicleRequest $request)
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $vehicle = null;
            DB::transaction(function () use ($user, $validated, &$vehicle) {
                $vehicle = Vehicle::create([
                    'user_id' => $user->id,
                    'plate_number' => $validated['plate_number'],
                    'brand' => $validated['brand'],
                    'model' => $validated['model'],
                ]);
            });

            return $this->sendSuccess(
                ['vehicle' => new VehicleResource($vehicle)],
                'تم إضافة السيارة بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Store Vehicle Error');
        }
    }


    /**
     * عرض تفاصيل سيارة محددة
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $vehicle = $user->vehicles()->find($id);

            if (!$vehicle) {
                return $this->sendError('السيارة غير موجودة أو لا تملك صلاحية الوصول إليها.', [], 404);
            }

            return $this->sendSuccess(
                ['vehicle' => new VehicleResource($vehicle)],
                'تم جلب بيانات السيارة بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Show Vehicle Error');
        }
    }

    /**
     * تحديث معلومات السيارة
     */
    public function update(UpdateVehicleRequest $request, $id)
    {
        try {
            $user = $request->user();
            $vehicle = $user->vehicles()->find($id);

            if (!$vehicle) {
                return $this->sendError('السيارة غير موجودة أو لا تملك صلاحية الوصول إليها.', [], 404);
            }

            $validated = $request->validated();

            DB::transaction(function () use ($vehicle, $validated) {
                $vehicle->update([
                    'plate_number' => $validated['plate_number'],
                    'brand' => $validated['brand'],
                    'model' => $validated['model'],
                ]);
            });

            return $this->sendSuccess(
                ['vehicle' => new VehicleResource($vehicle->fresh())],
                'تم تحديث بيانات السيارة بنجاح.'
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Update Vehicle Error');
        }
    }


    /**
     * حذف سيارة
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $vehicle = $user->vehicles()->find($id);

            if (!$vehicle) {
                return $this->sendError('السيارة غير موجودة أو لا تملك صلاحية الوصول إليها.', [], 404);
            }

            // TODO: التحقق من عدم وجود حجوزات نشطة (سنضيفها لاحقاً عند بناء Bookings)
            // $activeBookings = $vehicle->bookings()->whereIn('status', ['pending', 'in_progress'])->exists();
            // if ($activeBookings) {
            //     return $this->sendError('لا يمكن حذف السيارة لأنها مرتبطة بحجوزات نشطة.', [], 400);
            // }

            DB::transaction(function () use ($vehicle) {
                $vehicle->delete();
            });

            return $this->sendSuccess([], 'تم حذف السيارة بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Delete Vehicle Error');
        }
    }
}
