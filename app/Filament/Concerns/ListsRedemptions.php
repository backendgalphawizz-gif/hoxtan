<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\RedemptionResource;
use App\Models\Redemption;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

trait ListsRedemptions
{
    use InteractsWithTable;

    abstract protected function redemptionStatuses(): ?array;

    public function table(Table $table): Table
    {
        $query = Redemption::query();

        $statuses = $this->redemptionStatuses();
        if ($statuses !== null) {
            $query->whereIn('status', $statuses);
        }

        return RedemptionResource::table(
            $table->query($query)
        );
    }
}
