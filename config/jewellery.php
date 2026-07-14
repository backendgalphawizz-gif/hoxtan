<?php

return [
    'genders' => [
        ['value' => 'men', 'label' => "Men's", 'icon' => 'male'],
        ['value' => 'women', 'label' => "Women's", 'icon' => 'female'],
    ],

    'purities' => [
        ['value' => '22K', 'label' => '22k'],
        ['value' => '18K', 'label' => '18k'],
        ['value' => '16K', 'label' => '16k'],
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
