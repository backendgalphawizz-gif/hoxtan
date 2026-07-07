<?php

namespace App\Support;

use App\Services\FilamentImmediateExportService;
use Filament\Actions;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Tables;
use Livewire\Component;

class FilamentExportActions
{
    /**
     * @param  class-string<\Filament\Actions\Exports\Exporter>  $exporterClass
     */
    public static function headerExport(string $exporterClass, string $module): Actions\Action
    {
        $action = Actions\Action::make('export');
        static::configureImmediateExportAction($action, $exporterClass, $module);

        return $action;
    }

    /**
     * @param  class-string<\Filament\Actions\Exports\Exporter>  $exporterClass
     */
    public static function tableExport(string $exporterClass, string $module): Tables\Actions\Action
    {
        $action = Tables\Actions\Action::make('export');
        static::configureImmediateExportAction($action, $exporterClass, $module);

        return $action;
    }

    /**
     * @param  class-string<\Filament\Actions\Exports\Exporter>  $exporterClass
     */
    protected static function configureImmediateExportAction(
        MountableAction $action,
        string $exporterClass,
        string $module,
    ): void {
        $action
            ->label('Export')
            ->color('gray')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (): bool => AdminPermissions::can($module, 'export'))
            ->modalHeading('Export data')
            ->modalSubmitActionLabel('Download')
            ->form([
                Forms\Components\Select::make('format')
                    ->label('File format')
                    ->options([
                        'csv' => 'CSV (.csv)',
                        'xlsx' => 'Excel (.xlsx)',
                    ])
                    ->default('csv')
                    ->required(),
            ])
            ->action(function (array $data, Component $livewire) use ($exporterClass) {
                return app(FilamentImmediateExportService::class)->download(
                    $livewire,
                    $exporterClass,
                    $data['format'] ?? 'csv',
                );
            });
    }
}