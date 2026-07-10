<?php

namespace App\Filament\Resources\JewelleryOrderResource\Pages;

use App\Filament\Resources\JewelleryOrderResource;
use App\Models\OldGoldBooking;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditSellJewelleryOrder extends EditRecord
{
    protected static string $resource = JewelleryOrderResource::class;

    public function mount(int|string $record): void
    {
        $this->record = OldGoldBooking::query()
            ->with(['user', 'payment', 'driver'])
            ->findOrFail($record);

        $this->authorizeAccess();
        $this->fillForm();
    }

    public function getRecord(): OldGoldBooking
    {
        /** @var OldGoldBooking $record */
        $record = $this->record;

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->url(fn (): string => JewelleryOrderResource::getUrl('view-sell', ['record' => $this->getRecord()])),
        ];
    }

    public function form(Form $form): Form
    {
        return JewelleryOrderResource::sellForm($form);
    }

    protected function handleRecordUpdate($record, array $data): OldGoldBooking
    {
        /** @var OldGoldBooking $record */
        $newStatus = (string) ($data['status'] ?? $record->status);

        if ($newStatus !== $record->status) {
            $now = now();

            match ($newStatus) {
                'accepted' => $data['accepted_at'] = $data['accepted_at'] ?? $now,
                'pickup_scheduling' => $data['pickup_scheduled_at'] = $data['pickup_scheduled_at'] ?? $now,
                'picked_up' => $data['picked_up_at'] = $data['picked_up_at'] ?? $now,
                'completed' => $data['completed_at'] = $data['completed_at'] ?? $now,
                default => null,
            };
        }

        $record->update($data);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return JewelleryOrderResource::getUrl('view-sell', ['record' => $this->getRecord()]);
    }
}
