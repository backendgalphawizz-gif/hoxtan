<?php

namespace App\Support;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class FilamentDateFilters
{
    /**
     * @return array{0: DatePicker, 1: DatePicker}
     */
    public static function rangeFields(
        string $fromField = 'from',
        string $toField = 'to',
        string $fromLabel = 'From Date',
        string $toLabel = 'To Date',
        bool $allowFuture = false,
    ): array {
        $from = DatePicker::make($fromField)
            ->label($fromLabel)
            ->native(false)
            ->closeOnDateSelection()
            ->live()
            ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($toField): void {
                $to = $get($toField);

                if ($state && $to && $state > $to) {
                    $set($toField, $state);
                }
            });

        $to = DatePicker::make($toField)
            ->label($toLabel)
            ->native(false)
            ->closeOnDateSelection()
            ->minDate(fn (Get $get): ?Carbon => filled($get($fromField))
                ? Carbon::parse($get($fromField))->startOfDay()
                : null);

        if (! $allowFuture) {
            $from->maxDate(now())->rules(['nullable', 'date', 'before_or_equal:today']);
            $to->maxDate(now())->rules(['nullable', 'date', 'before_or_equal:today']);
        } else {
            $from->rules(['nullable', 'date']);
            $to->rules(['nullable', 'date']);
        }

        return [$from, $to];
    }

    public static function tableFilter(
        string $name,
        string $column,
        string $label = 'Date Range',
        bool $allowFuture = false,
    ): Filter {
        return Filter::make($name)
            ->label($label)
            ->form(static::rangeFields(allowFuture: $allowFuture))
            ->columns(2)
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if ($from = $data['from'] ?? null) {
                    $indicators['from'] = 'From '.Carbon::parse($from)->format('d M Y');
                }

                if ($to = $data['to'] ?? null) {
                    $indicators['to'] = 'To '.Carbon::parse($to)->format('d M Y');
                }

                return $indicators;
            })
            ->query(fn (Builder $query, array $data): Builder => static::applyRange($query, $data, $column));
    }

    public static function applyRange(
        Builder $query,
        array $data,
        string $column,
        string $fromField = 'from',
        string $toField = 'to',
    ): Builder {
        $from = $data[$fromField] ?? null;
        $to = $data[$toField] ?? null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return $query
            ->when($from, fn (Builder $q): Builder => $q->whereDate($column, '>=', $from))
            ->when($to, fn (Builder $q): Builder => $q->whereDate($column, '<=', $to));
    }

    public static function singleDateField(
        string $name = 'date',
        string $label = 'Date',
        bool $required = true,
        bool $allowFuture = false,
    ): DatePicker {
        $field = DatePicker::make($name)
            ->label($label)
            ->native(false)
            ->closeOnDateSelection();

        if ($required) {
            $field->required();
        }

        if (! $allowFuture) {
            $field
                ->maxDate(now())
                ->rules($required
                    ? ['required', 'date', 'before_or_equal:today']
                    : ['nullable', 'date', 'before_or_equal:today']);
        } else {
            $field->rules($required ? ['required', 'date'] : ['nullable', 'date']);
        }

        return $field;
    }
}
