<?php

namespace App\Filament\Pages\GoldRate;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Services\MetalRateService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class LiveSync extends Page
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'gold_live_sync';
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Gold Rate Management';

    protected static ?string $navigationLabel = 'Live Gold Rate Sync';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'gold-live-sync';

    protected static string $view = 'admin.rates.live-sync';

    public ?array $currentRate = null;

    public function mount(MetalRateService $rates): void
    {
        $this->loadCurrentRate($rates);
    }

    public function getSubheading(): ?string
    {
        return 'Fetch and apply the latest live gold rate from the market feed.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sync Live Rate')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Sync Live Gold Rate')
                ->modalDescription('This will fetch the current market gold rate and save it as the active rate.')
                ->action(function (MetalRateService $rates): void {
                    $record = $rates->syncLiveRate('gold');
                    $this->loadCurrentRate($rates);

                    Notification::make()
                        ->title('Live gold rate synced')
                        ->body('₹'.number_format((float) $record->rate_per_gram, 2).'/g applied.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function loadCurrentRate(MetalRateService $rates): void
    {
        $active = $rates->getActiveRate('gold');
        $live = $rates->getLiveRate('gold');

        $this->currentRate = [
            'active' => $active?->rate_per_gram,
            'live' => $live,
            'live_source' => $rates->getLiveRateSource('gold'),
            'updated_at' => $active?->updated_at?->format('M d, Y g:i A'),
            'source' => $active?->source,
        ];
    }
}
