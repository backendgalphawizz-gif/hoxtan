<?php

namespace App\Filament\Pages\SilverRate;

use App\Models\MetalRate;
use App\Services\MetalRateService;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class LiveSync extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Silver Rate Management';

    protected static ?string $navigationLabel = 'Live Silver Rate Sync';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'silver-live-sync';

    protected static string $view = 'admin.rates.live-sync';

    public ?array $currentRate = null;

    public string $metalLabel = 'Silver';

    public function mount(MetalRateService $rates): void
    {
        $this->loadCurrentRate($rates);
    }

    public function getSubheading(): ?string
    {
        return 'Fetch and apply the latest live silver rate from the market feed.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sync Live Rate')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Sync Live Silver Rate')
                ->modalDescription('This will fetch the current market silver rate and save it as the active rate.')
                ->action(function (MetalRateService $rates): void {
                    $record = $rates->syncLiveRate('silver');
                    $this->loadCurrentRate($rates);

                    Notification::make()
                        ->title('Live silver rate synced')
                        ->body('₹'.number_format((float) $record->rate_per_gram, 2).'/g applied.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function loadCurrentRate(MetalRateService $rates): void
    {
        $active = $rates->getActiveRate('silver');
        $live = $rates->getLiveRate('silver');

        $this->currentRate = [
            'active' => $active?->rate_per_gram,
            'live' => $live,
            'updated_at' => $active?->updated_at?->format('M d, Y g:i A'),
            'source' => $active?->source,
        ];
    }
}
