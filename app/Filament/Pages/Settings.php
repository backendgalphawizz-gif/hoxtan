<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Services\AppSettingService;
use App\Support\AdminPermissions;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page implements HasForms
{
    use InteractsWithAdminPermissions;
    use InteractsWithForms;

    protected static function adminPermissionModule(): string
    {
        return 'settings';
    }

    public static function canAccess(): bool
    {
        return AdminPermissions::canViewModule('settings');
    }

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'settings';

    protected static string $view = 'admin.settings.index';

    public ?array $data = [];

    public function mount(AppSettingService $settings): void
    {
        $values = [];

        foreach ($settings->definitions() as $key => $definition) {
            $value = $settings->get($key, $definition['default'] ?? '');

            if (($definition['type'] ?? 'text') === 'toggle') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            $values[$key] = $value;
        }

        $this->form->fill($values);
    }

    public function getSubheading(): ?string
    {
        return 'Configure GST, Metals-API options, and application details without code changes.';
    }

    public function form(Form $form): Form
    {
        $definitions = app(AppSettingService::class)->definitions();
        $groups = [
            'general' => 'General',
            'finance' => 'Finance',
            'referrals' => 'Refer & Earn / Bonuses',
            'metal_rates' => 'Metal Rates',
        ];

        $sections = [];

        foreach ($groups as $groupKey => $groupLabel) {
            $fields = [];

            foreach ($definitions as $key => $definition) {
                if (($definition['group'] ?? 'general') !== $groupKey) {
                    continue;
                }

                $field = match ($definition['type'] ?? 'text') {
                    'number' => TextInput::make($key)
                        ->numeric()
                        ->step(0.01),
                    'email' => TextInput::make($key)->email(),
                    'url' => TextInput::make($key)->url(),
                    'textarea' => Textarea::make($key)->rows(3),
                    'toggle' => Toggle::make($key)
                        ->label($definition['label'] ?? $key)
                        ->inline(false),
                    default => TextInput::make($key),
                };

                if (($definition['type'] ?? 'text') !== 'toggle') {
                    $field = $field->label($definition['label'] ?? $key);
                }

                $fields[] = $field
                    ->helperText($definition['description'] ?? null);
            }

            if ($fields !== []) {
                $sections[] = Section::make($groupLabel)->schema($fields)->columns(2);
            }
        }

        return $form->schema($sections)->statePath('data');
    }

    public function save(AppSettingService $settings): void
    {
        $data = $this->form->getState();
        $settings->setMany($data);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
