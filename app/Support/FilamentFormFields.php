<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;

class FilamentFormFields
{
    public const NAME_REGEX = '/^[A-Za-z]+(?:\s+[A-Za-z]+)*$/';

    public const PHONE_REGEX = '/^\d{10}$/';

    public const EMAIL_REGEX = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}(\.[a-zA-Z]{2,})?$/';

    public const PINCODE_REGEX = '/^\d{6}$/';

    public const PAN_REGEX = '/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/';

    public const AADHAAR_REGEX = '/^\d{12}$/';

    /** Indian driving licence: SS RR YYYY NNNNNNN (e.g. MH1420110012345), optional spaces/hyphens. */
    public const LICENCE_NO_REGEX = '/^[A-Za-z]{2}[-\s]?[0-9]{2}[-\s]?[0-9]{4}[-\s]?[0-9]{7}$/';

    public static function sanitizeName(?string $state, int $max = 32): ?string
    {
        if ($state === null) {
            return null;
        }

        $cleaned = preg_replace('/[^A-Za-z\s]+/', '', $state) ?? '';
        $cleaned = preg_replace('/\s+/', ' ', $cleaned) ?? '';
        $cleaned = trim($cleaned);

        if ($cleaned === '') {
            return null;
        }

        return mb_substr($cleaned, 0, $max);
    }

    public static function sanitizeDigits(?string $state, int $max = 10): ?string
    {
        if ($state === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $state) ?? '';

        if ($digits === '') {
            return null;
        }

        return substr($digits, 0, $max);
    }

    public static function name(
        string $field = 'name',
        ?string $label = 'Name',
        bool $required = true,
        int $maxLength = 32,
    ): TextInput {
        $maxLength = max(1, $maxLength);

        return TextInput::make($field)
            ->label($label)
            ->required($required)
            ->maxLength($maxLength)
            ->regex(self::NAME_REGEX)
            ->live(onBlur: true)
            ->afterStateUpdated(function (TextInput $component, ?string $state) use ($maxLength): void {
                // Keep spaces while typing; strip anything that is not a letter/space.
                if ($state === null) {
                    return;
                }

                $typed = preg_replace('/[^A-Za-z\s]+/', '', $state) ?? '';
                $typed = mb_substr($typed, 0, $maxLength);

                if ($typed !== $state) {
                    $component->state($typed);
                }
            })
            ->dehydrateStateUsing(fn (?string $state): ?string => self::sanitizeName($state, $maxLength))
            ->validationMessages([
                'regex' => 'Only alphabets and spaces are allowed.',
                'max' => "Cannot exceed {$maxLength} characters.",
            ])
            ->extraInputAttributes([
                'maxlength' => (string) $maxLength,
                'pattern' => '[A-Za-z\\s]+',
                'title' => "Letters and spaces only, up to {$maxLength} characters",
                'oninput' => "this.value=this.value.replace(/[^A-Za-z\\s]/g,'').slice(0,{$maxLength})",
            ]);
    }

    public static function fullName(
        string $field = 'full_name',
        ?string $label = 'Full Name',
        bool $required = true,
        int $maxLength = 32,
    ): TextInput {
        return self::name($field, $label, $required, $maxLength);
    }

    public static function relation(string $field = 'nominee_relation', ?string $label = 'Relation', bool $required = false): TextInput
    {
        return self::name($field, $label, $required, 32)
            ->validationMessages([
                'regex' => 'Relation may only contain alphabets and spaces (max 32 characters).',
                'max' => 'Relation cannot exceed 32 characters.',
            ]);
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
            ->afterStateUpdated(function (TextInput $component, ?string $state): void {
                $digits = self::sanitizeDigits($state, 10) ?? '';

                if ($digits !== (string) $state) {
                    $component->state($digits === '' ? null : $digits);
                }
            })
            ->dehydrateStateUsing(fn (?string $state): ?string => self::sanitizeDigits($state, 10))
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
                'oninput' => "this.value=this.value.replace(/\\D/g,'').slice(0,10)",
            ]);
    }

    public static function city(string $field = 'city', ?string $label = 'City', bool $required = false): TextInput
    {
        return self::name($field, $label, $required)
            ->validationMessages([
                'regex' => 'City must contain only alphabets and spaces.',
                'max' => 'City cannot exceed 32 characters.',
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
            ->afterStateUpdated(function (TextInput $component, ?string $state): void {
                $digits = self::sanitizeDigits($state, 6) ?? '';

                if ($digits !== (string) $state) {
                    $component->state($digits === '' ? null : $digits);
                }
            })
            ->dehydrateStateUsing(fn (?string $state): ?string => self::sanitizeDigits($state, 6))
            ->validationMessages([
                'regex' => 'Pincode must be exactly 6 digits.',
            ])
            ->extraInputAttributes([
                'maxlength' => '6',
                'inputmode' => 'numeric',
                'pattern' => '[0-9]{6}',
                'oninput' => "this.value=this.value.replace(/\\D/g,'').slice(0,6)",
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
                'oninput' => "this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,10)",
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
            ->afterStateUpdated(function (TextInput $component, ?string $state): void {
                $digits = self::sanitizeDigits($state, 12) ?? '';

                if ($digits !== (string) $state) {
                    $component->state($digits === '' ? null : $digits);
                }
            })
            ->dehydrateStateUsing(fn (?string $state): ?string => self::sanitizeDigits($state, 12))
            ->validationMessages([
                'regex' => 'Aadhaar must be exactly 12 digits.',
            ])
            ->extraInputAttributes([
                'maxlength' => '12',
                'inputmode' => 'numeric',
                'pattern' => '[0-9]{12}',
                'oninput' => "this.value=this.value.replace(/\\D/g,'').slice(0,12)",
            ]);
    }

    public static function normalizeLicenceNumber(?string $state): ?string
    {
        if (! filled($state)) {
            return null;
        }

        return strtoupper((string) preg_replace('/[\s\-]+/', '', $state));
    }

    public static function licenceNumber(string $field = 'licence_no', ?string $label = 'Licence No', bool $required = true): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->required($required)
            ->maxLength(18)
            ->regex(self::LICENCE_NO_REGEX)
            ->unique(ignoreRecord: true)
            ->live(onBlur: true)
            ->afterStateUpdated(function (TextInput $component, ?string $state): void {
                $normalized = self::normalizeLicenceNumber($state);
                if ($normalized !== null && $normalized !== $state) {
                    $component->state($normalized);
                }
            })
            ->dehydrateStateUsing(fn (?string $state): ?string => self::normalizeLicenceNumber($state))
            ->helperText('Indian DL format: 2 letters + 2 digits + year + 7 digits (e.g. MH1420110012345).')
            ->validationMessages([
                'regex' => 'Enter a valid licence number (e.g. MH1420110012345).',
                'unique' => 'This licence number is already registered to another driver.',
            ])
            ->extraInputAttributes([
                'maxlength' => '18',
                'style' => 'text-transform: uppercase',
                'title' => 'Example: MH1420110012345',
            ])
            ->placeholder('MH1420110012345');
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
            ->afterStateUpdated(function (TextInput $component, ?string $state) use ($length): void {
                $digits = self::sanitizeDigits($state, $length) ?? '';

                if ($digits !== (string) $state) {
                    $component->state($digits === '' ? null : $digits);
                }
            })
            ->dehydrateStateUsing(fn (?string $state): ?string => self::sanitizeDigits($state, $length))
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
                'oninput' => "this.value=this.value.replace(/\\D/g,'').slice(0,{$length})",
            ]);
    }
}
