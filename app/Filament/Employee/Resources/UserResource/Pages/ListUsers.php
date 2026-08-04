<?php

namespace App\Filament\Employee\Resources\UserResource\Pages;

use App\Filament\Employee\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getSubheading(): ?string
    {
        return 'Create and manage users registered from your employee account.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add User')
                ->icon('heroicon-o-plus'),
        ];
    }
}
