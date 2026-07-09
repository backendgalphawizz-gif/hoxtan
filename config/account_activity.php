<?php

return [
    'order_statuses' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],

    'order_filters' => [
        ['value' => 'all', 'label' => 'All'],
        ['value' => 'pending', 'label' => 'Pending'],
        ['value' => 'processing', 'label' => 'Processing'],
        ['value' => 'completed', 'label' => 'Completed'],
        ['value' => 'cancelled', 'label' => 'Cancelled'],
    ],

    'transaction_filters' => [
        ['value' => 'all', 'label' => 'All'],
        ['value' => 'buy', 'label' => 'Buy'],
        ['value' => 'sell', 'label' => 'Sell'],
        ['value' => 'wallet', 'label' => 'Wallet'],
        ['value' => 'sig', 'label' => 'SIG'],
        ['value' => 'jewellery', 'label' => 'Jewellery'],
        ['value' => 'redemption', 'label' => 'Redemption'],
    ],

    'investment_statuses' => [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],

    'wallet_sources' => [
        'admin' => 'Admin',
        'investment' => 'Investment',
        'redemption' => 'Redemption',
        'refund' => 'Refund',
        'welcome_bonus' => 'Welcome Bonus',
        'referral_bonus' => 'Referral Bonus',
        'other' => 'Other',
    ],
];
