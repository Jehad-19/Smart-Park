<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SendNotification extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static string $view = 'filament.pages.send-notification';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->required(),
                Textarea::make('body')
                    ->required(),
                Select::make('users')
                    ->multiple()
                    ->options(User::all()->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Select users (leave empty for all)'),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        
        $users = empty($data['users']) ? User::all() : User::whereIn('id', $data['users'])->get();

        foreach ($users as $user) {
            Notification::make()
                ->title($data['title'])
                ->body($data['body'])
                ->success()
                ->sendToDatabase($user);
        }

        Notification::make()
            ->title('Notifications sent successfully')
            ->success()
            ->send();
            
        $this->form->fill();
    }
}
