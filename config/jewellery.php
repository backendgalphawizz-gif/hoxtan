<?php

return [
    'genders' => [
        ['value' => 'men', 'label' => "Men's", 'icon' => 'male'],
        ['value' => 'women', 'label' => "Women's", 'icon' => 'female'],
    ],

    /*
    | Gold / silver purity options for admin product forms and app filters.
    | Prefer metal-specific lists (`purities.gold` / `purities.silver`).
    | Legacy flat list kept for callers that do not pass metal_type.
    */
    'purities' => [
        'gold' => [
            ['value' => '24K', 'label' => '24K'],
            ['value' => '22K', 'label' => '22K'],
        ],
        'silver' => [
            ['value' => '999', 'label' => '999 Fine Silver'],
            ['value' => '925', 'label' => '925 Sterling Silver'],
        ],
        // Fallback when metal_type is unknown (union of common gold options).
        'default' => [
            ['value' => '22K', 'label' => '22K'],
            ['value' => '999', 'label' => '999 Fine Silver'],
            ['value' => '925', 'label' => '925 Sterling Silver'],
            
        ],
    ],

    'weight' => [
        'min' => 1.0,
        'max' => 100.0,
        'unit' => 'gm',
        'min_placeholder' => 'e.g. 1.0',
        'max_placeholder' => 'e.g. 50.0',
    ],

    'budget' => [
        'min' => 10000,
        'max' => 100000,
        'currency' => 'INR',
        'min_placeholder' => 'e.g. ₹10,000',
        'max_placeholder' => 'e.g. ₹50,000',
    ],

    'search_placeholder' => 'Search for rings, necklaces...',
];
