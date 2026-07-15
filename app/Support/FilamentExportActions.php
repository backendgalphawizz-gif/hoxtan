<?php

namespace App\Support;

use App\Services\FilamentImmediateExportService;
use Filament\Actions;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Component;

class FilamentExportActions
{
    /**
     * @param  class-string<\Filament\Actions\Exports\Exporter>  $exporterClass
     */
    public static function headerExport(
        string $exporterClass,
        string $module,
        bool $requireSelection = false,
    ): Actions\Action {
        $action = Actions\Action::make('export');
        static::configureImmediateExportAction($action, $exporterClass, $module, $requireSelection);

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
    public static function bulkExport(string $exporterClass, string $module): Tables\Actions\BulkAction
    {
        $action = Tables\Actions\BulkAction::make('exportSelected')
            ->label('Export selected')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->visible(fn (): bool => AdminPermissions::can($module, 'export'))
            ->modalHeading('Export selected records')
            ->modalDescription('Only the rows you checked will be included in the download.')
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
            ->action(function (array $data, EloquentCollection $records, Component $livewire) use ($exporterClass) {
                if ($records->isEmpty()) {
                    Notification::make()
                        ->title('No records selected')
                        ->body('Select one or more rows using the checkboxes, then export.')
                        ->warning()
                        ->send();

                    return null;
                }

                return app(FilamentImmediateExportService::class)->download(
                    $livewire,
                    $exporterClass,
                    $data['format'] ?? 'csv',
                    $records->pluck($records->first()?->getKeyName() ?? 'id')->all(),
                );
            });

        return $action;
    }

    /**
     * @param  class-string<\Filament\Actions\Exports\Exporter>  $exporterClass
     */
    protected static function configureImmediateExportAction(
        MountableAction $action,
        string $exporterClass,
        string $module,
        bool $requireSelection = false,
    ): void {
        $action
            ->label($requireSelection ? 'Export selected' : 'Export')
            ->color('gray')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (): bool => AdminPermissions::can($module, 'export'))
            ->modalHeading($requireSelection ? 'Export selected records' : 'Export data')
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
            ->action(function (array $data, Component $livewire) use ($exporterClass, $requireSelection) {
                $selectedKeys = $livewire instanceof HasTable
                    ? array_values($livewire->selectedTableRecords ?? [])
                    : [];

                if ($requireSelection && $selectedKeys === []) {
                    Notification::make()
                        ->title('Select records to export')
                        ->body('Select one or more rows using the checkboxes, then click Export selected.')
                        ->warning()
                        ->send();

                    return null;
                }

                return app(FilamentImmediateExportService::class)->download(
                    $livewire,
                    $exporterClass,
                    $data['format'] ?? 'csv',
                    $requireSelection ? $selectedKeys : null,
                );
            });
    }
}
