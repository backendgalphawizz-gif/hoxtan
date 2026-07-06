<?php

namespace App\Support;

class PhoneRules
{
    public static function normalize(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }

    public static function rules(): array
    {
        return ['required', 'string', 'regex:/^\d{10}$/'];
    }

    public static function messages(): array
    {
        return [
            'phone.regex' => 'Mobile number must be a valid 10-digit Indian number.',
        ];
    }
}
