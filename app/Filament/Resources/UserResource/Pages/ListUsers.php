<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Exports\UserReportExporter;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getSubheading(): ?string
    {
        return 'Manage users, account verification, and account blocking.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(UserReportExporter::class)
                ->label('Export')
                ->color('gray')
                ->icon('heroicon-o-arrow-down-tray'),
            Actions\CreateAction::make()
                ->label('Add User')
                ->icon('heroicon-o-plus'),
        ];
    }
}
