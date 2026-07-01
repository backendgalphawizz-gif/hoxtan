<?php

namespace App\Support;

use App\Services\AppSettingService;

class MpinRules
{
    public static function length(): int
    {
        return max(4, min(6, app(AppSettingService::class)->getInt('mpin_length', 4)));
    }

  public static function validationRules(string $field = 'mpin', bool $confirmed = true): array
    {
        $length = static::length();

        $rules = [
            $field => ['required', 'string', "digits:{$length}", 'regex:/^\d+$/'],
        ];

        if ($confirmed) {
            $rules[$field.'_confirmation'] = ['required', 'same:'.$field];
        }

        return $rules;
    }

    public static function validationMessages(): array
    {
        $length = static::length();

        return [
            'mpin.digits' => "MPIN must be exactly {$length} digits.",
            'mpin.regex' => 'MPIN must contain only numbers.',
            'mpin_confirmation.same' => 'MPIN confirmation does not match.',
        ];
    }
}
