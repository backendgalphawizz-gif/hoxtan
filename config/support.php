<?php

return [
    'ticket_prefix' => 'HXT',

    'categories' => [
        ['value' => 'general', 'label' => 'General Inquiry'],
        ['value' => 'vault_access', 'label' => 'Vault Access'],
        ['value' => 'liquidation', 'label' => 'Liquidation'],
        ['value' => 'kyc', 'label' => 'KYC & Verification'],
        ['value' => 'redemption', 'label' => 'Redemption & Delivery'],
        ['value' => 'billing', 'label' => 'Billing & Payments'],
        ['value' => 'technical', 'label' => 'Technical Issue'],
    ],

    'statuses' => [
        'open' => 'UNDER REVIEW',
        'pending' => 'PENDING ACTION',
        'resolved' => 'RESOLVED',
        'closed' => 'CLOSED',
    ],

    'filters' => [
        ['value' => 'all', 'label' => 'All'],
        ['value' => 'open', 'label' => 'Open'],
        ['value' => 'pending', 'label' => 'Pending'],
        ['value' => 'resolved', 'label' => 'Resolved'],
    ],

    'response_time' => '< 15 Mins',
    'support_hours' => '24/7 Global',
];
