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

    'order_tracking_steps' => [
        ['key' => 'placed', 'label' => 'Order Placed'],
        ['key' => 'processing', 'label' => 'Processing'],
        ['key' => 'shipped', 'label' => 'Shipped'],
        ['key' => 'delivered', 'label' => 'Delivered'],
    ],

    'order_status_tracking_index' => [
        'pending' => 0,
        'processing' => 1,
        'completed' => 3,
        'failed' => 0,
        'cancelled' => 0,
    ],

    /*
    | EMI jewellery track timeline (order detail / track screen).
    | Index: 0 placed, 1 reserved, 2 waiting/completed EMI, 3 ready, 4 delivered.
    */
    'emi_order_tracking_steps' => [
        ['key' => 'placed', 'label' => 'Order Placed'],
        ['key' => 'reserved', 'label' => 'Reserved for You'],
        [
            'key' => 'emi_waiting',
            'label' => 'Waiting for EMI Completion',
            'completed_label' => 'EMI Completed',
        ],
        ['key' => 'ready_for_delivery', 'label' => 'Ready for Delivery'],
        ['key' => 'delivered', 'label' => 'Delivered'],
    ],

    'transaction_filters' => [
        ['value' => 'all', 'label' => 'All'],
        ['value' => 'buy', 'label' => 'Buy'],
        ['value' => 'sell', 'label' => 'Sell'],
        ['value' => 'wallet', 'label' => 'Wallet'],
        ['value' => 'sig', 'label' => 'SIG'],
        ['value' => 'jewellery', 'label' => 'Jewellery'],
        ['value' => 'redemption', 'label' => 'Redemption'],
        ['value' => 'gold', 'label' => 'Gold'],
        ['value' => 'silver', 'label' => 'Silver'],
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
