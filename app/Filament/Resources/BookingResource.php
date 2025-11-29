<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Filament\Resources\BookingResource\RelationManagers;
use App\Models\Booking;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_id')
                    ->label('المستخدم')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('spot_id')
                    ->label('الموقف')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('vehicle_id')
                    ->label('المركبة')
                    ->required()
                    ->numeric(),
                Forms\Components\DateTimePicker::make('start_time')
                    ->label('وقت البدء')
                    ->required(),
                Forms\Components\DateTimePicker::make('end_time')
                    ->label('وقت الانتهاء')
                    ->required(),
                Forms\Components\TextInput::make('duration_minutes')
                    ->label('المدة (دقيقة)')
                    ->numeric(),
                Forms\Components\TextInput::make('total_price')
                    ->label('السعر الإجمالي')
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
                Tables\Columns\TextColumn::make('user.name')
                    ->label('اسم المستخدم')
                    ->sortable(),
                Tables\Columns\TextColumn::make('spot.spot_number')
                    ->label('رقم الموقف')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicle.brand')
                    ->label('نوع السيارة')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('وقت البدء')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('وقت الانتهاء')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('المدة (دقيقة)')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('السعر الإجمالي')
                    ->numeric()
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
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
                Tables\Actions\ViewAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            // 'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
