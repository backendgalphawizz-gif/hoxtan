<?php

namespace App\Providers;

use App\Models\Investment;
use App\Observers\InvestmentObserver;
use App\Support\FilamentAdminForm;
use App\Support\FilamentFormat;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Investment::observe(InvestmentObserver::class);

        FilamentAdminForm::configureRequiredFields();

        // Avoid intl dependency — Filament's ->money() and ->numeric() require ext-intl
        TextColumn::macro('inr', function (int $decimals = 2): TextColumn {
            /** @var TextColumn $this */
            return $this->formatStateUsing(
                fn ($state) => FilamentFormat::inr($state, $decimals)
            );
        });

        TextColumn::macro('grams', function (int $decimals = 4): TextColumn {
            /** @var TextColumn $this */
            return $this->formatStateUsing(
                fn ($state) => FilamentFormat::grams($state, $decimals)
            );
        });

        TextColumn::macro('formattedNumber', function (int $decimals = 0): TextColumn {
            /** @var TextColumn $this */
            return $this->formatStateUsing(
                fn ($state) => FilamentFormat::number($state, $decimals)
            );
        });
    }
}
