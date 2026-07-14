<?php

return [
    'title' => 'Withdraw Assets',
    'select_title' => 'Select Assets to Withdraw',

    'min_amount' => 1000,

    // Metal can be withdrawn only after this many hours from purchase/credit.
    'holding_period_hours' => 48,

    // Shown on withdraw + holdings screens (encourage holding).
    'hold_bonus_percent' => 1,
    'hold_bonus_message' => 'If you continue to hold, you get 1% extra on your holdings.',
    'holding_period_message' => 'Withdrawal is available only after 48 hours from purchase.',

    'auto_approve_hours' => 2,

    'preset_amounts' => [5000, 10000, 25000, 50000],

    'input_modes' => [
        ['value' => 'currency', 'label' => 'BY AMOUNT'],
        ['value' => 'weight', 'label' => 'BY WEIGHT'],
    ],

    'assets' => [
        [
            'value' => 'gold',
            'label' => 'Gold',
            'screen_title' => 'Withdraw Gold',
        ],
        [
            'value' => 'silver',
            'label' => 'Silver',
            'screen_title' => 'Withdraw Silver',
        ],
        [
            'value' => 'sig',
            'label' => 'SIG',
            'screen_title' => 'Withdraw SIG',
        ],
    ],

    'note' => 'Your withdrawal amount is transferred instantly to your registered bank account.',
    'min_amount_note' => 'Minimum withdrawal amount is ₹1,000.',
];
