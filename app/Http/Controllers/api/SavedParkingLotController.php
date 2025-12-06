<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ParkingLotResource;
use App\Models\ParkingLot;
use Illuminate\Http\Request;

class SavedParkingLotController extends BaseApiController
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (!$this->userSupportsSaving($user)) {
                return $this->sendError('نوع المستخدم غير مخول لعرض المحفوظات.', [], 403);
            }

            $savedLots = $user->savedParkingLots()
                ->where('status', 'active')
                ->get();

            $savedLots->each(function (ParkingLot $lot) {
                $this->decorateLot($lot, true);
            });

            return $this->sendSuccess([
                'parking_lots' => ParkingLotResource::collection($savedLots),
                'total' => $savedLots->count(),
            ], 'تم جلب المواقف المحفوظة بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Saved Parking Lots Index Error');
        }
    }

    public function store(Request $request, int $parkingLotId)
    {
        try {
            $user = $request->user();
            if (!$this->userSupportsSaving($user)) {
                return $this->sendError('نوع المستخدم غير مخول لحفظ المواقف.', [], 403);
            }

            $parkingLot = ParkingLot::where('status', 'active')->find($parkingLotId);
            if (!$parkingLot) {
                return $this->sendError('الموقف غير موجود أو غير نشط.', [], 404);
            }

            $user->savedParkingLots()->syncWithoutDetaching([$parkingLot->id]);

            $this->decorateLot($parkingLot, true);

            return $this->sendSuccess([
                'parking_lot' => new ParkingLotResource($parkingLot),
            ], 'تم حفظ الموقف بنجاح.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Save Parking Lot Error');
        }
    }

    public function destroy(Request $request, int $parkingLotId)
    {
        try {
            $user = $request->user();
            if (!$this->userSupportsSaving($user)) {
                return $this->sendError('نوع المستخدم غير مخول لإدارة المحفوظات.', [], 403);
            }

            $user->savedParkingLots()->detach($parkingLotId);

            $parkingLot = ParkingLot::find($parkingLotId);
            if ($parkingLot) {
                $this->decorateLot($parkingLot, false);
                return $this->sendSuccess([
                    'parking_lot' => new ParkingLotResource($parkingLot),
                ], 'تم إزالة الموقف من المحفوظات.');
            }

            return $this->sendSuccess([
                'parking_lot_id' => $parkingLotId,
            ], 'تم إزالة الموقف من المحفوظات.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Remove Saved Parking Lot Error');
        }
    }

    private function decorateLot(ParkingLot $lot, bool $isSaved): ParkingLot
    {
        $lot->available_spots = $lot->availableSpotsCount();
        $lot->total_spots = $lot->totalSpotsCount();
        $lot->is_saved = $isSaved;

        return $lot;
    }

    private function userSupportsSaving($user): bool
    {
        return $user && method_exists($user, 'savedParkingLots');
    }
}
