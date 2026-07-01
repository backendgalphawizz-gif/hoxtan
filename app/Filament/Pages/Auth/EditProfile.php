<?php

namespace App\Filament\Pages\Auth;

use App\Support\FilamentFormFields;
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
                    ->description('Update your admin account details.')
                    ->schema([
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
}
