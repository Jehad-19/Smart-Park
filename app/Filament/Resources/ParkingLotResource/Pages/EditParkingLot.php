<?php

namespace App\Filament\Resources\ParkingLotResource\Pages;

use App\Filament\Resources\ParkingLotResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParkingLot extends EditRecord
{
    protected static string $resource = ParkingLotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
