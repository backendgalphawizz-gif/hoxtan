<?php

namespace App\Filament\Employee\Pages;

use App\Filament\Employee\Widgets\MyEmployeesTableWidget;
use App\Filament\Employee\Widgets\TeamStatsOverviewWidget;
use App\Filament\Employee\Widgets\TeamUsersTableWidget;
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

    public function getSubheading(): ?string
    {
        /** @var Employee|null $actor */
        $actor = Auth::guard('employee')->user();

        if ($actor?->isStaff()) {
            return 'Overview of your employees and their users';
        }

        return 'Overview of users you have created';
    }

    public function getColumns(): int|string|array
    {
        return 1;
    }

    public function getWidgets(): array
    {
        return [
            TeamStatsOverviewWidget::class,
            MyEmployeesTableWidget::class,
            TeamUsersTableWidget::class,
        ];
    }
}
