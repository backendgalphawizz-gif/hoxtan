<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Exports\UserReportExporter;
use App\Filament\Resources\UserResource;
use App\Support\FilamentExportActions;
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
            FilamentExportActions::headerExport(UserReportExporter::class, 'users'),
            Actions\CreateAction::make()
                ->label('Add User')
                ->icon('heroicon-o-plus'),
        ];
    }
}
