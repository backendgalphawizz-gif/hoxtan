<?php

namespace App\Filament\Employee\Pages;

use App\Models\Employee;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getTitle(): string
    {
        /** @var Employee|null $actor */
        $actor = Auth::guard('employee')->user();

        return $actor?->isStaff() ? 'Staff Dashboard' : 'Employee Dashboard';
    }
}
