<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParkingLotResource\Pages;
use App\Filament\Resources\ParkingLotResource\RelationManagers;
use App\Models\ParkingLot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ParkingLotResource extends Resource
{
    protected static ?string $model = ParkingLot::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->label('الوصف')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('address')
                    ->label('العنوان')
                    ->required()
                    ->columnSpanFull(),
                // Forms\Components\TextInput::make('latitude')
                //     ->required()
                //     ->numeric(),
                // Forms\Components\TextInput::make('longitude')
                //     ->required()
                //     ->numeric(),
                Forms\Components\TextInput::make('price_per_hour')
                    ->label('السعر للساعة')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('status')
                    ->label('الحالة')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('العنوان')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('latitude')
                //     ->numeric()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('longitude')
                //     ->numeric()
                //     ->sortable(),
                Tables\Columns\TextColumn::make('price_per_hour')
                    ->label('السعر للساعة')
                    ->numeric()
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'maintenance' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SpotsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParkingLots::route('/'),
            'create' => Pages\CreateParkingLot::route('/create'),
            'edit' => Pages\EditParkingLot::route('/{record}/edit'),
        ];
    }
}
