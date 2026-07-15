<?php

return [
    'preset_amounts' => [100, 500, 1000, 2000, 5000],

    'min_amount' => 100,

    'frequencies' => [
        ['value' => 'daily', 'label' => 'Daily'],
        ['value' => 'weekly', 'label' => 'Weekly'],
        ['value' => 'monthly', 'label' => 'Monthly'],
    ],

    'metal_types' => [
        ['value' => 'gold', 'label' => 'Gold 24K 99.9% PURE'],
        ['value' => 'silver', 'label' => 'Silver'],
    ],

    'statuses' => [
        'active' => 'Active',
        'paused' => 'Paused',
        'stopped' => 'Stopped',
    ],

    'installment_statuses' => [
        'pending' => 'PENDING',
        'success' => 'SUCCESS',
        'failed' => 'FAILED',
        'withdrawal_pending' => 'WITHDRAWAL PENDING',
        'withdrawal' => 'WITHDRAWN',
        'withdrawal_rejected' => 'WITHDRAWAL REJECTED',
    ],

    'manage_actions' => [
        [
            'key' => 'pause',
            'label' => 'Pause Investment',
            'description' => 'Temporarily stop automatic deductions.',
            'endpoint' => 'POST /api/v1/sig/pause',
            'available_when' => ['active'],
            'modal' => [
                'title' => 'Pause SIG?',
                'message' => 'Automatic deductions will stop until you resume your SIG plan.',
                'confirm_label' => 'Pause SIG',
            ],
        ],
        [
            'key' => 'resume',
            'label' => 'Resume Investment',
            'description' => 'Restart automatic deductions.',
            'endpoint' => 'POST /api/v1/sig/resume',
            'available_when' => ['paused'],
            'modal' => [
                'title' => 'Resume SIG?',
                'message' => 'Your next auto-debit will be scheduled after you resume.',
                'confirm_label' => 'Resume SIG',
            ],
        ],
        [
            'key' => 'stop',
            'label' => 'Stop Investment',
            'description' => 'Permanently cancel this SIG plan.',
            'endpoint' => 'POST /api/v1/sig/stop',
            'available_when' => ['active', 'paused'],
            'modal' => [
                'title' => 'Stop SIG permanently?',
                'message' => 'This cannot be undone. You will need to activate a new SIG plan to invest again. You can then withdraw your SIG balance to your bank account.',
                'confirm_label' => 'Stop SIG',
                'destructive' => true,
            ],
        ],
        [
            'key' => 'withdraw',
            'label' => 'Withdraw SIG',
            'description' => 'Request payout of remaining SIG metal to your registered bank account.',
            'endpoint' => 'POST /api/v1/withdraw',
            'payload_example' => ['amount' => 1400],
            'available_when' => ['stopped'],
            'modal' => [
                'title' => 'Withdraw SIG?',
                'message' => 'Enter amount only. Request goes to admin and auto-approves after 2 hours to your bank account.',
                'confirm_label' => 'Request Withdrawal',
            ],
        ],
    ],
];
