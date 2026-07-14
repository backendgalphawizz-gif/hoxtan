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
    'hold_bonus_message' => 'If you continue to hold, you get 1% extra on your holdings.',

    'my_purchases' => [
        'label' => 'MY PURCHASES',
        'endpoint' => '/api/v1/transactions',
        'filter' => 'buy',
    ],

    'series' => [
        ['key' => 'market_value', 'label' => 'Current Value', 'style' => 'solid'],
        ['key' => 'invested_value', 'label' => 'Invested Value', 'style' => 'dashed'],
    ],
];
