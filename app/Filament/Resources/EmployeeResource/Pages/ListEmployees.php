<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string
    {
        return 'Staff';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Staff'),
        ];
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()?->where('role', Employee::ROLE_STAFF);
    }
}
