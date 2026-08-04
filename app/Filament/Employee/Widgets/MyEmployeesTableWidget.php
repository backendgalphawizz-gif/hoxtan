<?php

namespace App\Filament\Employee\Widgets;

use App\Filament\Employee\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MyEmployeesTableWidget extends BaseWidget
{
    protected static ?string $heading = 'My Employees';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var Employee|null $actor */
        $actor = Auth::guard('employee')->user();

        return $actor?->isStaff() ?? false;
    }

    protected function getTableHeading(): ?string
    {
        /** @var Employee|null $actor */
        $actor = Auth::guard('employee')->user();
        $count = $actor
            ? $actor->createdEmployees()->where('role', Employee::ROLE_EMPLOYEE)->count()
            : 0;

        return 'My Employees ('.$count.')';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->employeesQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('employee_code')
                    ->label('Code')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_users_count')
                    ->label('Users')
                    ->counts('createdUsers'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25])
            ->headerActions([
                Tables\Actions\Action::make('manage')
                    ->label('Manage Employees')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(EmployeeResource::getUrl('index')),
                Tables\Actions\Action::make('create')
                    ->label('Add Employee')
                    ->icon('heroicon-o-plus')
                    ->url(EmployeeResource::getUrl('create')),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Employee $record): string => EmployeeResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('No employees yet')
            ->emptyStateDescription('Create employees from My Employees to build your team.');
    }

    /**
     * @return Builder<Employee>
     */
    protected function employeesQuery(): Builder
    {
        /** @var Employee|null $actor */
        $actor = Auth::guard('employee')->user();

        return Employee::query()
            ->where('created_by_employee_id', $actor?->id)
            ->where('role', Employee::ROLE_EMPLOYEE);
    }
}
