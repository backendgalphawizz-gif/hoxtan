<?php

namespace App\Filament\Employee\Widgets;

use App\Models\Employee;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TeamStatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        /** @var Employee|null $actor */
        $actor = Auth::guard('employee')->user();

        return $actor?->isStaff() ? 2 : 1;
    }

    protected function getStats(): array
    {
        /** @var Employee|null $actor */
        $actor = Auth::guard('employee')->user();

        if (! $actor) {
            return [];
        }

        $stats = [];

        if ($actor->isStaff()) {
            $employeeCount = $actor->createdEmployees()
                ->where('role', Employee::ROLE_EMPLOYEE)
                ->count();

            $stats[] = Stat::make('Total Employees', number_format($employeeCount))
                ->description('Employees under you')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary')
                ->url(\App\Filament\Employee\Resources\EmployeeResource::getUrl('index'));
        }

        $userCount = $this->teamUsersQuery($actor)->count();

        $userStat = Stat::make('Total Users', number_format($userCount))
            ->description($actor->isStaff() ? 'Users under your employees' : 'Users you created')
            ->descriptionIcon('heroicon-m-users')
            ->color('success');

        if ($actor->isTeamEmployee()) {
            $userStat->url(\App\Filament\Employee\Resources\UserResource::getUrl('index'));
        }

        $stats[] = $userStat;

        return $stats;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    protected function teamUsersQuery(Employee $actor)
    {
        if ($actor->isStaff()) {
            $employeeIds = $actor->createdEmployees()
                ->where('role', Employee::ROLE_EMPLOYEE)
                ->pluck('id');

            return User::query()->whereIn('created_by_employee_id', $employeeIds);
        }

        return User::query()->where('created_by_employee_id', $actor->id);
    }
}
