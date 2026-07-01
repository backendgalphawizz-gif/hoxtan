<?php

namespace App\Support;

use Filament\Actions;
use Filament\Actions\Exports\Exporter;

class FilamentExportActions
{
    /**
     * @param  class-string<Exporter>  $exporterClass
     */
    public static function headerExport(string $exporterClass, string $module): Actions\ExportAction
    {
        return Actions\ExportAction::make()
            ->exporter($exporterClass)
            ->label('Export')
            ->color('gray')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (): bool => AdminPermissions::can($module, 'export'));
    }

    /**
     * @param  class-string<Exporter>  $exporterClass
     */
    public static function tableExport(string $exporterClass, string $module): \Filament\Tables\Actions\ExportAction
    {
        return \Filament\Tables\Actions\ExportAction::make()
            ->exporter($exporterClass)
            ->label('Export')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (): bool => AdminPermissions::can($module, 'export'));
    }
}
