<?php

namespace App\Filament\Resources\SigPlanResource\Pages;

use App\Filament\Resources\SigPlanResource;
use App\Models\SigPlan;
use App\Services\SigPlanService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSigPlan extends ViewRecord
{
    protected static string $resource = SigPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pause')
                ->label('Pause SIG')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn (SigPlan $record): bool => $record->status === 'active' && SigPlanResource::canEdit($record))
                ->requiresConfirmation()
                ->modalHeading('Pause SIG?')
                ->modalDescription('Automatic deductions will stop until the SIG is resumed.')
                ->action(function (SigPlan $record, SigPlanService $service): void {
                    $service->pause($record);
                    Notification::make()->title('SIG paused')->warning()->send();
                }),
            Actions\Action::make('resume')
                ->label('Resume SIG')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (SigPlan $record): bool => $record->status === 'paused' && SigPlanResource::canEdit($record))
                ->requiresConfirmation()
                ->modalHeading('Resume SIG?')
                ->modalDescription('The next auto-debit will be scheduled based on the plan frequency.')
                ->action(function (SigPlan $record, SigPlanService $service): void {
                    $service->resume($record);
                    Notification::make()->title('SIG resumed')->success()->send();
                }),
            Actions\Action::make('stop')
                ->label('Stop SIG')
                ->icon('heroicon-o-stop')
                ->color('danger')
                ->visible(fn (SigPlan $record): bool => $record->status !== 'stopped' && SigPlanResource::canEdit($record))
                ->requiresConfirmation()
                ->modalHeading('Stop SIG permanently?')
                ->modalDescription('This cannot be undone. The client will need to activate a new SIG.')
                ->modalSubmitActionLabel('Stop SIG')
                ->action(function (SigPlan $record, SigPlanService $service): void {
                    $service->stop($record);
                    Notification::make()->title('SIG stopped')->danger()->send();
                }),
            Actions\EditAction::make(),
        ];
    }
}
