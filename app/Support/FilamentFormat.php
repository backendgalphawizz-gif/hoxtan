<?php

namespace App\Support;

class FilamentFormat
{
    public static function inr(mixed $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return '₹'.number_format((float) $value, $decimals);
    }

    public static function grams(mixed $value, int $decimals = 4): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, $decimals).' g';
    }

    public static function number(mixed $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, $decimals);
    }
}
