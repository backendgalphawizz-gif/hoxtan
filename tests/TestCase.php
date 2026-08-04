<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * User with KYC approved for buy/sell/hold/SIG/jewellery transactions.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithTransactionKyc(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'kyc_status' => 'approved',
        ], $attributes));

        $user->kycDetail()->create([
            'full_name' => $user->name ?? 'Test User',
            'pan_number' => 'ABCDE1234F',
            'pan_verification_status' => 'verified',
            'pan_verified_at' => now(),
            'aadhaar_number' => '123456789012',
            'aadhaar_verification_status' => 'verified',
            'aadhaar_verified_at' => now(),
            'bank_name' => 'Test Bank',
            'account_holder_name' => $user->name ?? 'Test User',
            'account_number' => '1234567890',
            'ifsc_code' => 'HDFC0001234',
            'bank_verification_status' => 'verified',
            'reviewed_at' => now(),
        ]);

        return $user->fresh(['kycDetail']);
    }
}
