<?php

return [
    'title' => 'Holdings Performance',
    'subtitle' => 'Historical growth of your portfolio.',

    'metal_types' => [
        ['value' => 'gold', 'label' => 'Gold'],
        ['value' => 'silver', 'label' => 'Silver'],
    ],

    'periods' => [
        ['value' => 12, 'label' => '12 MONTHS'],
        ['value' => 24, 'label' => '24 MONTHS'],
        ['value' => 36, 'label' => '36 MONTHS'],
    ],

    'default_metal_type' => 'gold',
    'default_months' => 12,

    'hold_bonus_percent' => 1,
    'hold_bonus_after_days' => 365,
    'hold_bonus_message' => 'Hold for 1 year from purchase date and get 1% extra on that lot\'s current value. Each purchase is tracked separately.',

    // Sell holding metal only after this many hours from purchase.
    'sell_after_hours' => (int) env('HOLDINGS_SELL_AFTER_HOURS', 48),
    // If admin does not act, auto-approve sell request after this many hours.
    'sell_auto_approve_hours' => (int) env('HOLDINGS_SELL_AUTO_APPROVE_HOURS', 2),
    'sell_after_message' => 'You can sell holding gold/silver only after 48 hours from purchase.',

    'my_purchases' => [
        'label' => 'MY PURCHASES',
        'endpoint' => '/api/v1/transactions',
        'filter' => 'buy',
    ],

    'series' => [
        ['key' => 'purchase_amount', 'label' => 'Purchase Amount', 'style' => 'dashed'],
        ['key' => 'current_rate_amount', 'label' => 'Current Rate Amount', 'style' => 'solid'],
    ],
];
