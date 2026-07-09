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

    /*
    | Ticket status → tracking stepper for the mobile Ticket Status screen.
    | Steps: Submitted → Under Review → Action Pending → Accepted → Resolved/Closed
    */
    'tracking_steps' => [
        ['key' => 'submitted', 'label' => 'Submitted'],
        ['key' => 'under_review', 'label' => 'Under Review'],
        ['key' => 'action_pending', 'label' => 'Action Pending'],
        ['key' => 'accepted', 'label' => 'Accepted'],
        ['key' => 'resolved', 'label' => 'Resolved/Closed'],
    ],

    'status_tracking_index' => [
        'open' => 1,      // Under Review
        'pending' => 2,   // Action Pending
        'resolved' => 4,  // Resolved/Closed (Accepted is passed when resolving)
        'closed' => 4,
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
