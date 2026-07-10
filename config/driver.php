<?php

return [
    'app_name' => 'HOXTAN Driver',

    'login' => [
        'title' => 'Driver Login',
        'subtitle' => 'Sign in to manage jewellery pickups, deliveries, and assigned tasks securely.',
        'verify_title' => 'Verify OTP',
        'verify_subtitle' => 'Enter the 4-digit OTP sent to your registered mobile number to continue.',
        'secure_label' => 'Secure Encrypted Access',
    ],

    'vehicle_types' => [
        ['value' => 'bike', 'label' => 'Bike'],
        ['value' => 'car', 'label' => 'Car'],
        ['value' => 'van', 'label' => 'Van'],
        ['value' => 'other', 'label' => 'Other'],
    ],

    'home' => [
        'tasks_preview_limit' => 5,
        'task_filters' => [
            'types' => [
                ['value' => 'all', 'label' => 'All Tasks'],
                ['value' => 'delivery', 'label' => 'Assigned Orders'],
                ['value' => 'pickup', 'label' => 'Jewellery Pickups'],
            ],
            'statuses' => [
                ['value' => 'all', 'label' => 'All'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'completed', 'label' => 'Completed'],
            ],
        ],
    ],

    'delivery' => [
        'otp_length' => 4,
        'failure_reasons' => [
            ['value' => 'customer_unavailable', 'label' => 'Customer unavailable'],
            ['value' => 'wrong_address', 'label' => 'Wrong Address'],
            ['value' => 'customer_refused', 'label' => 'Customer Refused'],
            ['value' => 'package_damaged', 'label' => 'Package Damaged'],
            ['value' => 'vehicle_issue', 'label' => 'Vehicle Issue'],
            ['value' => 'other', 'label' => 'Other'],
        ],
        'statuses' => [
            'accepted' => ['label' => 'Accepted', 'color' => 'muted'],
            'picked_up' => ['label' => 'Picked Up', 'color' => 'warning'],
            'delivered' => ['label' => 'Delivered', 'color' => 'success'],
            'cancelled' => ['label' => 'Cancelled', 'color' => 'danger'],
        ],
    ],

    'deliveries' => [
        'search_placeholder' => 'Search for orders ID, Sell ID...',
        'per_page' => 10,
        'tabs' => [
            ['value' => 'all', 'label' => 'All'],
            ['value' => 'order', 'label' => 'Orders'],
            ['value' => 'pickup', 'label' => 'Pickups'],
        ],
        'filters' => [
            ['value' => 'all', 'label' => 'All Orders'],
            ['value' => 'new', 'label' => 'New Orders'],
            ['value' => 'accepted', 'label' => 'Accepted'],
            ['value' => 'picked_up', 'label' => 'Picked Up'],
            ['value' => 'delivered', 'label' => 'Delivered'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
        ],
    ],
];
