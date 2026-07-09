<?php

return [
    'booking_prefix' => 'SELL',

    'metal_types' => [
        ['value' => 'gold', 'label' => 'Gold'],
        ['value' => 'silver', 'label' => 'Silver'],
    ],

    'purities' => [
        'gold' => ['24K', '22K', '20K', '18K'],
        'silver' => ['999', '925'],
    ],

    'purity_factors' => [
        '24K' => 1.0,
        '22K' => 0.916,
        '20K' => 0.833,
        '18K' => 0.75,
        '999' => 1.0,
        '925' => 0.925,
    ],

    'identity_owners' => [
        ['value' => 'own_name', 'label' => 'In my name'],
        ['value' => 'someone_else', 'label' => 'In someone else\'s name'],
    ],

    'sell_locations' => [
        ['value' => 'at_home', 'label' => 'At Home'],
        ['value' => 'at_bank', 'label' => 'At Bank'],
    ],

    'document_types' => [
        'id_proof' => ['label' => 'ID Proof', 'required' => true],
        'selfie' => ['label' => 'Selfie (Account Holder)', 'required' => true],
        'purchase_receipt' => ['label' => 'Purchase Receipt', 'required_for' => 'at_home'],
        'bank_receipt' => ['label' => 'Bank Receipt', 'required_for' => 'at_bank'],
    ],

    'statuses' => [
        'pending' => 'Pending for Acceptance',
        'accepted' => 'Request Accepted',
        'pickup_scheduling' => 'Pickup Scheduling',
        'picked_up' => 'Jewellery Picked',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'failed' => 'Failed',
    ],

    'list_filters' => [
        ['value' => 'all', 'label' => 'All'],
        ['value' => 'pending', 'label' => 'Pending'],
        ['value' => 'accepted', 'label' => 'Accepted'],
        ['value' => 'cancelled', 'label' => 'Cancelled'],
    ],

    'tracking_steps' => [
        ['key' => 'pending', 'label' => 'Pending for Acceptance'],
        ['key' => 'accepted', 'label' => 'Request Accepted'],
        ['key' => 'pickup_scheduling', 'label' => 'Pickup Scheduling'],
        ['key' => 'picked_up', 'label' => 'Jewellery Picked'],
        ['key' => 'completed', 'label' => 'Completed'],
    ],

    'status_tracking_index' => [
        'pending' => 0,
        'accepted' => 1,
        'pickup_scheduling' => 2,
        'picked_up' => 3,
        'completed' => 4,
        'cancelled' => 0,
        'failed' => 0,
    ],
];
