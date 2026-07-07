<?php

namespace App\Filament\Pages\Reports\Concerns;

use App\Support\AdminPermissions;
use App\Support\FilamentExportActions;

trait InteractsWithReportExport
{
    protected static function exportModule(): string
    {
        return static::adminPermissionModule();
    }

    /**
     * @param  class-string<\Filament\Actions\Exports\Exporter>  $exporterClass
     */
    protected static function reportExportAction(string $exporterClass): \Filament\Tables\Actions\ExportAction
    {
        return FilamentExportActions::tableExport($exporterClass, static::exportModule());
    }

    protected static function canExportReport(): bool
    {
        return AdminPermissions::can(static::exportModule(), 'export');
    }
}
