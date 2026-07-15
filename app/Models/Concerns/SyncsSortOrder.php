<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Keeps sort_order as a unique sequence within a scope.
 * Changing A from 3 → 2 swaps with whoever currently holds 2.
 *
 * @mixin Model
 */
trait SyncsSortOrder
{
    public static function bootSyncsSortOrder(): void
    {
        static::creating(function (Model $model): void {
            $order = (int) ($model->getAttribute('sort_order') ?? 0);

            if ($order <= 0) {
                $model->setAttribute('sort_order', static::nextSortOrder($model));

                return;
            }

            $order = max(1, $order);
            $model->setAttribute('sort_order', $order);

            /** @var Builder $query */
            $query = static::query()->where('sort_order', '>=', $order);
            static::applySortOrderScope($query, $model);

            // Insert into an existing slot: bump later items so the sequence stays unique.
            static::withoutEvents(function () use ($query): void {
                $query->orderByDesc('sort_order')->get()->each(function (Model $row): void {
                    $row->forceFill([
                        'sort_order' => ((int) $row->getAttribute('sort_order')) + 1,
                    ])->saveQuietly();
                });
            });
        });

        static::updating(function (Model $model): void {
            if (! $model->isDirty('sort_order')) {
                return;
            }

            $newOrder = max(1, (int) $model->getAttribute('sort_order'));
            $model->setAttribute('sort_order', $newOrder);

            $oldOrder = (int) $model->getOriginal('sort_order');

            if ($oldOrder === $newOrder) {
                return;
            }

            /** @var Builder $query */
            $query = static::query()
                ->whereKeyNot($model->getKey())
                ->where('sort_order', $newOrder);

            static::applySortOrderScope($query, $model);

            $occupant = $query->first();

            if ($occupant instanceof Model) {
                // Swap positions without re-firing sort sync (avoids recursion).
                $occupant->timestamps = false;
                static::withoutEvents(function () use ($occupant, $oldOrder): void {
                    $occupant->forceFill(['sort_order' => max(1, $oldOrder)])->saveQuietly();
                });
            }
        });
    }

    public static function nextSortOrder(?Model $model = null): int
    {
        /** @var Builder $query */
        $query = static::query();

        if ($model instanceof Model) {
            static::applySortOrderScope($query, $model);
        }

        return max(1, ((int) $query->max('sort_order')) + 1);
    }

    /**
     * Rebuild 1..N sequence when orders are 0 or duplicated.
     */
    public static function resequenceSortOrders(?callable $groupBy = null): void
    {
        DB::transaction(function () use ($groupBy): void {
            if ($groupBy === null) {
                static::resequenceGroup(static::query()->orderBy('sort_order')->orderBy('id'));

                return;
            }

            $groupBy();
        });
    }

    protected static function resequenceGroup(Builder $query): void
    {
        $needs = (clone $query)->where('sort_order', '<=', 0)->exists()
            || (clone $query)
                ->select('sort_order')
                ->groupBy('sort_order')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

        if (! $needs) {
            return;
        }

        $position = 1;

        (clone $query)->orderBy('sort_order')->orderBy('id')->get()->each(function (Model $row) use (&$position): void {
            static::withoutEvents(function () use ($row, &$position): void {
                $row->forceFill(['sort_order' => $position])->saveQuietly();
            });
            $position++;
        });
    }

    /**
     * Scope conflicting rows (e.g. same parent category). Override in model when needed.
     */
    protected static function applySortOrderScope(Builder $query, Model $model): void
    {
        // Global by default (e.g. jewellery categories).
    }
}
