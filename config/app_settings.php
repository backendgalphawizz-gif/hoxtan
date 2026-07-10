<?php

return [
    'definitions' => [
        'app_name' => [
            'group' => 'general',
            'label' => 'Application Name',
            'type' => 'text',
            'description' => 'Displayed across admin branding and notifications.',
            'default' => 'HOXTAN',
        ],
        'support_email' => [
            'group' => 'general',
            'label' => 'Support Email',
            'type' => 'email',
            'description' => 'Contact email shown to users.',
            'default' => 'support@hoxtandigigold.com',
        ],
        'support_phone' => [
            'group' => 'general',
            'label' => 'Support Phone (Toll Free)',
            'type' => 'text',
            'description' => 'Toll-free support number shown in website footer and app.',
            'default' => '18005693934',
        ],
        'gst_rate_percent' => [
            'group' => 'finance',
            'label' => 'GST Rate (%)',
            'type' => 'number',
            'description' => 'GST percentage applied on buy transactions (split equally as CGST + SGST).',
            'default' => '3',
        ],
        'jewellery_delivery_days' => [
            'group' => 'jewellery',
            'label' => 'Jewellery Delivery Days',
            'type' => 'number',
            'description' => 'Estimated days until jewellery order delivery (from order date).',
            'default' => '10',
        ],
        'metals_api_gold_symbol' => [
            'group' => 'metal_rates',
            'label' => 'Metals-API Gold Symbol (India)',
            'type' => 'text',
            'description' => 'India gold symbol from Metals-API (e.g. VISA-24k, VIJA-22k). Set METALS_API_KEY in .env — rates are fetched live from Metals-API, not entered manually.',
            'default' => 'VISA-24k',
        ],
        'metal_api_timeout_seconds' => [
            'group' => 'metal_rates',
            'label' => 'Metals-API Timeout (seconds)',
            'type' => 'number',
            'description' => 'Maximum wait time when fetching live gold and silver rates from Metals-API.',
            'default' => '10',
        ],
        'welcome_bonus_enabled' => [
            'group' => 'referrals',
            'label' => 'Welcome Bonus',
            'type' => 'toggle',
            'description' => 'Credit new users with the welcome bonus on registration.',
            'default' => '1',
        ],
        'welcome_bonus_amount' => [
            'group' => 'referrals',
            'label' => 'Welcome Bonus Amount (₹)',
            'type' => 'number',
            'description' => 'Wallet credit given to every new user on registration.',
            'default' => '50',
        ],
        'referral_bonus_enabled' => [
            'group' => 'referrals',
            'label' => 'Refer & Earn',
            'type' => 'toggle',
            'description' => 'Reward referrers when someone registers with their code.',
            'default' => '1',
        ],
        'referral_bonus_amount' => [
            'group' => 'referrals',
            'label' => 'Referral Bonus Amount (₹)',
            'type' => 'number',
            'description' => 'Wallet credit given to the referrer when a new user signs up with their code.',
            'default' => '100',
        ],
    ],
];
