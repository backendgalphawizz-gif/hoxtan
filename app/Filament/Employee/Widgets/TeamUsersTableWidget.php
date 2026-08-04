<?php

namespace App\Filament\Employee\Widgets;

use App\Filament\Employee\Resources\UserResource;
use App\Models\Employee;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TeamUsersTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Users';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): ?string
    {
        /** @var Employee|null $actor */
        $actor = Auth::guard('employee')->user();
        $count = $actor ? $this->usersQuery($actor)->count() : 0;

        return ($actor?->isStaff() ? 'Team Users' : 'My Users').' ('.$count.')';
    }

    public function table(Table $table): Table
    {
        /** @var Employee|null $actor */
        $actor = Auth::guard('employee')->user();
        $isStaff = $actor?->isStaff() ?? false;

        return $table
            ->query($actor ? $this->usersQuery($actor) : User::query()->whereRaw('1 = 0'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Mobile')
                    ->searchable(),
                Tables\Columns\TextColumn::make('createdByEmployee.name')
                    ->label('Created By')
                    ->placeholder('—')
                    ->visible($isStaff)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('kyc_status')
                    ->label('KYC')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'under_review', 'submitted' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->trueIcon('heroicon-o-no-symbol')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25])
            ->headerActions([
                Tables\Actions\Action::make('manage')
                    ->label('Manage Users')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (): bool => ! $isStaff && UserResource::canAccess())
                    ->url(fn (): string => UserResource::getUrl('index')),
            ])
            ->emptyStateHeading('No users yet')
            ->emptyStateDescription($isStaff
                ? 'Users created by your employees will appear here.'
                : 'Create users from My Users.');
    }

    /**
     * @return Builder<User>
     */
    protected function usersQuery(Employee $actor): Builder
    {
        $query = User::query()->with('createdByEmployee:id,name');

        if ($actor->isStaff()) {
            $employeeIds = $actor->createdEmployees()
                ->where('role', Employee::ROLE_EMPLOYEE)
                ->pluck('id');

            return $query->whereIn('created_by_employee_id', $employeeIds);
        }

        return $query->where('created_by_employee_id', $actor->id);
    }
}
