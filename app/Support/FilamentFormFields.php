<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;

class FilamentFormFields
{
    public const NAME_REGEX = '/^[A-Za-z\s]+$/';

    public const PHONE_REGEX = '/^\d{10}$/';

    public const EMAIL_REGEX = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}(\.[a-zA-Z]{2,})?$/';

    public const PINCODE_REGEX = '/^\d{6}$/';

    public const PAN_REGEX = '/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/';

    public const AADHAAR_REGEX = '/^\d{12}$/';

    public static function name(string $field = 'name', ?string $label = 'Name', bool $required = true): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->required($required)
            ->maxLength(32)
            ->regex(self::NAME_REGEX)
            ->live(onBlur: true)
            ->validationMessages([
                'regex' => 'Only alphabets and spaces are allowed.',
                'max' => 'Name cannot exceed 32 characters.',
            ])
            ->extraInputAttributes([
                'maxlength' => '32',
                'pattern' => '[A-Za-z\\s]+',
                'title' => 'Letters and spaces only, up to 32 characters',
            ]);
    }

    public static function fullName(string $field = 'full_name', ?string $label = 'Full Name', bool $required = true): TextInput
    {
        return self::name($field, $label, $required);
    }

    public static function email(string $field = 'email', ?string $label = 'Email Address', bool $required = true): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->email()
            ->required($required)
            ->maxLength(255)
            ->regex(self::EMAIL_REGEX)
            ->live(onBlur: true)
            ->validationMessages([
                'email' => 'Enter a valid email address.',
                'regex' => 'Use a valid email format (e.g. name@gmail.com, name@company.co.in).',
            ])
            ->extraInputAttributes([
                'title' => 'Valid email with domain extension like .com, .co.in, .in',
            ]);
    }

    public static function mobile(string $field = 'phone', ?string $label = 'Mobile Number', bool $required = false): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->tel()
            ->required($required)
            ->minLength(10)
            ->maxLength(10)
            ->regex(self::PHONE_REGEX)
            ->live(onBlur: true)
            ->validationMessages([
                'regex' => 'Mobile number must be exactly 10 digits.',
                'min' => 'Mobile number must be exactly 10 digits.',
                'max' => 'Mobile number must be exactly 10 digits.',
            ])
            ->extraInputAttributes([
                'maxlength' => '10',
                'inputmode' => 'numeric',
                'pattern' => '[0-9]{10}',
                'title' => '10 digit mobile number only',
            ]);
    }

    public static function city(string $field = 'city', ?string $label = 'City', bool $required = false): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->required($required)
            ->maxLength(32)
            ->regex(self::NAME_REGEX)
            ->live(onBlur: true)
            ->validationMessages([
                'regex' => 'City must contain only alphabets and spaces.',
            ])
            ->extraInputAttributes([
                'maxlength' => '32',
                'pattern' => '[A-Za-z\\s]+',
            ]);
    }

    public static function state(string $field = 'state', ?string $label = 'State', bool $required = false): TextInput
    {
        return self::city($field, $label, $required);
    }

    public static function pincode(string $field = 'pincode', ?string $label = 'Pincode', bool $required = false): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->required($required)
            ->minLength(6)
            ->maxLength(6)
            ->regex(self::PINCODE_REGEX)
            ->live(onBlur: true)
            ->validationMessages([
                'regex' => 'Pincode must be exactly 6 digits.',
            ])
            ->extraInputAttributes([
                'maxlength' => '6',
                'inputmode' => 'numeric',
                'pattern' => '[0-9]{6}',
            ]);
    }

    public static function panNumber(string $field = 'pan_number', ?string $label = 'PAN Number', bool $required = false): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->required($required)
            ->maxLength(10)
            ->regex(self::PAN_REGEX)
            ->live(onBlur: true)
            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtoupper($state) : null)
            ->validationMessages([
                'regex' => 'Invalid PAN format (e.g. ABCDE1234F).',
            ])
            ->extraInputAttributes([
                'maxlength' => '10',
                'style' => 'text-transform: uppercase',
            ]);
    }

    public static function aadhaarNumber(string $field = 'aadhaar_number', ?string $label = 'Aadhaar Number', bool $required = false): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->required($required)
            ->minLength(12)
            ->maxLength(12)
            ->regex(self::AADHAAR_REGEX)
            ->live(onBlur: true)
            ->validationMessages([
                'regex' => 'Aadhaar must be exactly 12 digits.',
            ])
            ->extraInputAttributes([
                'maxlength' => '12',
                'inputmode' => 'numeric',
                'pattern' => '[0-9]{12}',
            ]);
    }

    public static function mpin(string $field = 'mpin', ?string $label = 'MPIN', bool $required = true): TextInput
    {
        $length = MpinRules::length();

        return TextInput::make($field)
            ->label($label)
            ->password()
            ->revealable()
            ->required($required)
            ->minLength($length)
            ->maxLength($length)
            ->regex('/^\d{'.$length.'}$/')
            ->live(onBlur: true)
            ->validationMessages([
                'regex' => "MPIN must be exactly {$length} digits.",
                'min' => "MPIN must be exactly {$length} digits.",
                'max' => "MPIN must be exactly {$length} digits.",
            ])
            ->extraInputAttributes([
                'maxlength' => (string) $length,
                'inputmode' => 'numeric',
                'pattern' => '[0-9]{'.$length.'}',
                'autocomplete' => 'off',
            ]);
    }
}
