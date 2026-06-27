<?php

namespace App\Support;

use App\Models\Investment;
use App\Models\KycDetail;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class NavigationBadgeCounts
{
    private const TTL_SECONDS = 60;

    public static function format(?int $count): ?string
    {
        return ($count ?? 0) > 0 ? (string) $count : null;
    }

    public static function usersAwaitingKycReview(): int
    {
        return Cache::remember('nav.users_awaiting_kyc', self::TTL_SECONDS, fn (): int => User::query()
            ->whereIn('kyc_status', ['pending', 'submitted', 'under_review'])
            ->count());
    }

    public static function pendingKycVerifications(): int
    {
        return Cache::remember('nav.pending_kyc_verifications', self::TTL_SECONDS, fn (): int => KycDetail::query()
            ->where('face_verification_status', 'pending')
            ->count());
    }

    public static function pendingRedemptions(): int
    {
        return Cache::remember('nav.pending_redemptions', self::TTL_SECONDS, fn (): int => Redemption::query()
            ->where('status', 'pending')
            ->count());
    }

    public static function dispatchedRedemptions(): int
    {
        return Cache::remember('nav.dispatched_redemptions', self::TTL_SECONDS, fn (): int => Redemption::query()
            ->where('status', 'dispatched')
            ->count());
    }

    public static function dispatchQueueRedemptions(): int
    {
        return Cache::remember('nav.dispatch_queue', self::TTL_SECONDS, fn (): int => Redemption::query()
            ->whereIn('status', ['approved', 'processing'])
            ->count());
    }

    public static function pendingBuyTransactions(): int
    {
        return Cache::remember('nav.pending_buy_transactions', self::TTL_SECONDS, fn (): int => Investment::query()
            ->where('type', 'buy')
            ->where('status', 'pending')
            ->count());
    }

    public static function pendingSellTransactions(): int
    {
        return Cache::remember('nav.pending_sell_transactions', self::TTL_SECONDS, fn (): int => Investment::query()
            ->where('type', 'sell')
            ->where('status', 'pending')
            ->count());
    }

    public static function clearCache(): void
    {
        foreach ([
            'nav.users_awaiting_kyc',
            'nav.pending_kyc_verifications',
            'nav.pending_redemptions',
            'nav.dispatched_redemptions',
            'nav.dispatch_queue',
            'nav.pending_buy_transactions',
            'nav.pending_sell_transactions',
        ] as $key) {
            Cache::forget($key);
        }
    }
}
