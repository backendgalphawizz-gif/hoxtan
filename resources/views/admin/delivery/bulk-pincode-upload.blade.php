<x-filament-panels::page>
    <form wire:submit="import" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Import Blocked Pincodes
        </x-filament::button>
    </form>
</x-filament-panels::page>
