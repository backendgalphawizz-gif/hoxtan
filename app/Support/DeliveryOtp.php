<?php

namespace App\Support;

class DeliveryOtp
{
    public static function generate(): string
    {
        $length = max(4, min(6, (int) config('otp.length', 4)));
        $max = (10 ** $length) - 1;
        $min = 10 ** ($length - 1);

        return (string) random_int($min, $max);
    }
}
