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
];
