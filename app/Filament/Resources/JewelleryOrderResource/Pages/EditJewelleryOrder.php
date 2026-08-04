<?php

namespace App\Filament\Resources\JewelleryOrderResource\Pages;

use App\Filament\Resources\JewelleryOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJewelleryOrder extends EditRecord
{
    protected static string $resource = JewelleryOrderResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if (JewelleryOrderResource::isOrderLocked($this->getRecord())) {
            $this->redirect(JewelleryOrderResource::getUrl('view', ['record' => $this->getRecord()]));

            return;
        }

        $this->authorizeAccess();
        $this->fillForm();
        $this->previousUrl = url()->previous();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
