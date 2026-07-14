<?php

return [
    'title' => 'Buy Gold & Silver',

    'input_modes' => [
        ['value' => 'currency', 'label' => 'BY CURRENCY'],
        ['value' => 'weight', 'label' => 'BY WEIGHT'],
    ],

    'metal_types' => [
        [
            'value' => 'gold',
            'label' => 'GOLD',
            'purity' => '24K',
            'purity_display' => '24K / 999.9 Fine',
            'quantity_label' => 'GRAMS OF Gold',
        ],
        [
            'value' => 'silver',
            'label' => 'SILVER',
            'purity' => '999',
            'purity_display' => '999 Fine Silver',
            'quantity_label' => 'GRAMS OF Silver',
        ],
    ],

    'preset_amounts' => [100, 500, 1000, 2000, 5000],

    'min_amount' => 100,

    'min_weight_grams' => 0.001,

    'max_weight_grams' => 10000,

    'gst_included_for_currency_mode' => true,

    'payment_methods' => [
        ['value' => 'razorpay', 'label' => 'Razorpay'],
        ['value' => 'direct', 'label' => 'Direct'],
        ['value' => 'wallet', 'label' => 'Wallet'],
    ],
];
