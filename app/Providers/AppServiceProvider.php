<?php

namespace App\Providers;

use App\Contracts\KycVerificationProvider;
use App\Models\Investment;
use App\Observers\InvestmentObserver;
use App\Policies\ExportPolicy;
use App\Services\KycVerificationProvider\StubKycVerificationProvider;
use App\Services\KycVerificationProvider\SurepassKycVerificationProvider;
use App\Support\AssetUrl;
use App\Support\FilamentAdminForm;
use App\Support\FilamentFormat;
use Filament\Forms\Components\FileUpload;
use Filament\Actions\Exports\Models\Export;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(KycVerificationProvider::class, function (): KycVerificationProvider {
            return match (config('kyc.provider', 'stub')) {
                'surepass' => new SurepassKycVerificationProvider,
                default => new StubKycVerificationProvider,
            };
        });
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        if (! $this->app->runningInConsole() && $this->app->bound('request')) {
            Config::set('filesystems.disks.public.url', AssetUrl::base().'/storage');
        }

        Gate::policy(Export::class, ExportPolicy::class);

        $this->guardMisconfiguredBroadcastConnection();

        $this->app->booted(function (): void {
            app(Router::class)->middlewareGroup('filament.actions', [
                'web',
                'auth:admin',
            ]);
        });

        Investment::observe(InvestmentObserver::class);

        FilamentAdminForm::configureRequiredFields();

        // Ensure Livewire temp upload directory exists (shared hosting often misses this).
        $livewireTmp = storage_path('app/livewire-tmp');
        if (! File::isDirectory($livewireTmp)) {
            File::makeDirectory($livewireTmp, 0755, true);
        }

        // Prevent UnableToRetrieveMetadata crashes when temp uploads disappear between Livewire requests.
        FileUpload::configureUsing(function (FileUpload $component): void {
            $component->fetchFileInformation(false);
        });

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

    protected function guardMisconfiguredBroadcastConnection(): void
    {
        $driver = (string) config('broadcasting.default', 'null');

        if (! in_array($driver, ['pusher', 'reverb'], true)) {
            return;
        }

        $connection = config("broadcasting.connections.{$driver}", []);

        if (! is_array($connection) || ! filled($connection['key'] ?? null)) {
            Config::set('broadcasting.default', 'null');
        }
    }
}
