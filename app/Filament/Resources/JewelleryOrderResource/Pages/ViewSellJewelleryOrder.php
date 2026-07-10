<?php

namespace App\Filament\Resources\JewelleryOrderResource\Pages;

use App\Filament\Resources\JewelleryOrderResource;
use App\Models\OldGoldBooking;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\ViewRecord;

class ViewSellJewelleryOrder extends ViewRecord
{
    protected static string $resource = JewelleryOrderResource::class;

    public function mount(int|string $record): void
    {
        $this->record = OldGoldBooking::query()
            ->with(['user', 'payment', 'driver'])
            ->findOrFail($record);

        $this->authorizeAccess();
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
            Actions\EditAction::make()
                ->url(fn (): string => JewelleryOrderResource::getUrl('edit-sell', ['record' => $this->getRecord()])),
        ];
    }

    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(JewelleryOrderResource::sellForm(
                $this->makeForm()
                    ->operation('view')
                    ->model($this->getRecord())
                    ->statePath($this->getFormStatePath())
                    ->columns($this->hasInlineLabels() ? 1 : 2)
                    ->inlineLabel($this->hasInlineLabels()),
            )),
        ];
    }
}
