<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Services\JewelleryKaratRateService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class JewelleryKaratRates extends Page implements HasForms
{
    use InteractsWithAdminPermissions;
    use InteractsWithForms;

    protected static function adminPermissionModule(): string
    {
        return 'jewellery_karat_rates';
    }

    protected static ?string $navigationIcon = 'heroicon-o-currency-rupee';

    protected static ?string $navigationGroup = 'Jewellery Management';

    protected static ?string $navigationLabel = 'Gold Karat Rates';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'jewellery-karat-rates';

    protected static ?string $title = 'Gold Karat Rates';

    protected static string $view = 'admin.jewellery.karat-rates';

    public ?array $data = [];

    public function mount(JewelleryKaratRateService $rates): void
    {
        $fill = [];

        foreach ($rates->allManaged() as $row) {
            $fill[$row->purity] = [
                'rate_per_gram' => $row->rate_per_gram,
                'is_active' => $row->exists ? (bool) $row->is_active : true,
            ];
        }

        $this->form->fill($fill);
    }

    public function getSubheading(): ?string
    {
        return 'Set ₹/g rates for 20K, 18K, 16K and 14K gold. Only active rates appear in the product purity dropdown and are used for pricing (not the metal API).';
    }

    public function form(Form $form): Form
    {
        $fields = [];

        foreach (app(JewelleryKaratRateService::class)->managedPurities() as $purity) {
            $fields[] = Section::make($purity.' Gold')
                ->schema([
                    TextInput::make("{$purity}.rate_per_gram")
                        ->label('Rate per Gram (₹)')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->prefix('₹'),
                    Toggle::make("{$purity}.is_active")
                        ->label('Active')
                        ->default(true),
                ])
                ->columns(2);
        }

        return $form
            ->schema($fields)
            ->statePath('data');
    }

    public function save(JewelleryKaratRateService $rates): void
    {
        $data = $this->form->getState();

        $rates->saveRates($data);

        Notification::make()
            ->title('Gold karat rates saved')
            ->success()
            ->send();
    }
}
