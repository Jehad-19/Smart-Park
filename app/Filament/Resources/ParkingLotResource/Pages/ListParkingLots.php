<?php

namespace App\Filament\Resources\ParkingLotResource\Pages;

use App\Filament\Resources\ParkingLotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParkingLots extends ListRecords
{
    protected static string $resource = ParkingLotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
