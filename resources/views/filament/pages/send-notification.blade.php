<x-filament-panels::page>
    <x-filament-panels::form wire:submit="submit">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit">
                Send Notification
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
