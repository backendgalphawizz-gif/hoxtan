<?php

namespace App\Filament\Pages\Auth;

use App\Support\FilamentFormFields;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

class EditProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return 'Admin Profile';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Profile Information')
                    ->description('Update your admin account details and profile photo.')
                    ->schema([
                        FileUpload::make('avatar')
                            ->label('Profile Photo')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('admin-avatars')
                            ->maxSize(2048)
                            ->imageEditor()
                            ->columnSpanFull(),
                        FilamentFormFields::name('name', 'Full Name')
                            ->required(),
                        FilamentFormFields::email()
                            ->label('Email Address')
                            ->required()
                            ->unique(ignoreRecord: true),
                    ])
                    ->columns(2),

                Section::make('Security')
                    ->description('Change your password to keep your account secure.')
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->columns(1),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('avatar', $data) && is_array($data['avatar'])) {
            $data['avatar'] = $data['avatar'][0] ?? null;
        }

        return $data;
    }
}
