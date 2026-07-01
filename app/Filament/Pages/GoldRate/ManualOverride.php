<?php

namespace App\Filament\Pages\GoldRate;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Services\MetalRateService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManualOverride extends Page implements HasForms
{
    use InteractsWithAdminPermissions;
    use InteractsWithForms;

    protected static function adminPermissionModule(): string
    {
        return 'gold_manual_override';
    }

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationGroup = 'Gold Rate Management';

    protected static ?string $navigationLabel = 'Manual Rate Override';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'gold-manual-override';

    protected static string $view = 'admin.rates.manual-override';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'rate_per_gram' => null,
            'is_active' => true,
            'notes' => null,
        ]);
    }

    public function getSubheading(): ?string
    {
        return 'Set a custom gold rate manually when market sync is unavailable.';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Override Details')
                    ->schema([
                        TextInput::make('rate_per_gram')
                            ->label('Rate per Gram (₹)')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('₹'),
                        Toggle::make('is_active')
                            ->label('Set as Active Rate')
                            ->default(true),
                        Textarea::make('notes')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(MetalRateService $rates): void
    {
        $data = $this->form->getState();

        $rates->applyManualRate(
            'gold',
            (float) $data['rate_per_gram'],
            (bool) ($data['is_active'] ?? true),
            $data['notes'] ?? null,
        );

        Notification::make()
            ->title('Manual gold rate saved')
            ->success()
            ->send();

        $this->form->fill([
            'rate_per_gram' => null,
            'is_active' => true,
            'notes' => null,
        ]);
    }
}
