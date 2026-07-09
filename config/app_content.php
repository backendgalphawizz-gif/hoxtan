<?php

return [
    'app_name' => 'HOXTAN',

    /*
    | Website pages managed from Admin → CMS → Static Pages.
    | Slug must match the static page record to appear on the website.
    */
    'website_pages' => [
        ['key' => 'about', 'slug' => 'about-us', 'label' => 'About Us'],
        ['key' => 'terms', 'slug' => 'terms-and-conditions', 'label' => 'Terms & Conditions'],
        ['key' => 'privacy', 'slug' => 'privacy-policy', 'label' => 'Privacy Policy'],
    ],

    'faqs_screen' => [
        'title' => 'FAQs',
        'headline' => 'How may we assist you?',
        'subtitle' => 'Access the support ecosystem for the world\'s most exclusive bullion exchange.',
        'search_placeholder' => 'Search for documentation, security protocol...',
        'section_title' => 'Frequently Asked Questions',
    ],

    'faq_categories' => [
        ['value' => 'trading', 'label' => 'TRADING', 'icon' => 'trading'],
        ['value' => 'kyc_account', 'label' => 'KYC & ACCOUNT', 'icon' => 'kyc_account'],
        ['value' => 'vault_security', 'label' => 'VAULT SECURITY', 'icon' => 'vault_security'],
        ['value' => 'withdrawals', 'label' => 'WITHDRAWALS', 'icon' => 'withdrawals'],
    ],

    'concierge' => [
        'title' => 'Private Concierge',
        'description' => 'For personalized assistance with large acquisitions or complex vaulting logistics, our elite support officers are standing by.',
        'contact_now_label' => 'Contact Now',
        'schedule_call_label' => 'Schedule Call',
    ],

    'terms' => [
        'slug' => 'terms-and-conditions',
        'title' => 'Terms & Conditions',
        'version' => 'V.4.02',
        'compliance_entity' => 'HOXTAN GLOBAL COMPLIANCE',
        'last_updated' => '2023-10-24',
        'last_updated_display' => 'October 24, 2023',
        'compliance_id' => 'HXT-992-DELTA',
        'acceptance_label' => 'I have read and agree to the Master Trading Agreement and Risk Disclosure.',
        'accept_button_label' => 'Accept & Continue',
        'sections' => [
            [
                'number' => '01',
                'title' => 'Introduction',
                'body' => 'Welcome to Hoxtan. This platform is a high-value trading environment designed for institutional-grade bullion transactions. By accessing Hoxtan Bullion Management services, you agree to comply with these terms governed by Hoxtan Vaults Ltd.',
            ],
            [
                'number' => '02',
                'title' => 'Trading Rules (Gold/Silver)',
                'body' => 'All trades are executed at real-time prices based on London Bullion Market Association (LBMA) spot prices plus the Hoxtan premium. Minimum trade size for Gold 999.9 is 1.0oz and for Silver 999 is 100oz. Cancellations of confirmed orders are strictly prohibited due to immediate market hedging.',
            ],
        ],
        'agreement_summary' => [
            ['label' => 'Jurisdiction', 'value' => 'Switzerland'],
            ['label' => 'Asset Custody', 'value' => 'Allocated / Segregated'],
            ['label' => 'Settlement', 'value' => 'T+0 Instant'],
            ['label' => 'Security Audit', 'value' => 'Quarterly / On-Demand'],
        ],
        'security_protocol' => [
            'title' => 'ELITE SECURITY PROTOCOL',
            'body' => 'Your assets are protected by 256-bit encryption and physical deep-vault storage.',
        ],
    ],

    'privacy' => [
        'slug' => 'privacy-policy',
        'title' => 'Privacy Policy',
        'tagline' => 'Your trust is our most valuable asset.',
        'privacy_support_email' => 'privacy@hoxtan.com',
        'sections' => [
            [
                'number' => '02',
                'title' => 'Information Collected',
                'items' => [
                    [
                        'title' => 'Personal Data',
                        'body' => 'Full legal name, residential address, and government-issued identification.',
                    ],
                    [
                        'title' => 'Financial Info',
                        'body' => 'Transaction history, bank account details, and credit card documentation.',
                    ],
                    [
                        'title' => 'Biometric KYC',
                        'body' => 'Facial recognition data and other biometric data for authentication and AML compliance.',
                    ],
                    [
                        'title' => 'Technical Data',
                        'body' => 'IP addresses, device identifiers, and encrypted session logs.',
                    ],
                ],
            ],
            [
                'number' => '03',
                'title' => 'Data Security',
                'items' => [
                    ['title' => 'Encryption', 'body' => 'AES-256 military-grade encryption and TLS for data in transit.'],
                    ['title' => 'Infrastructure', 'body' => 'Hosted in Tier 4 data centers.'],
                    ['title' => 'Architecture', 'body' => 'Zero-knowledge architecture for sensitive credentials.'],
                    ['title' => 'Wallet Security', 'body' => 'Multi-signature wallets for fund management.'],
                    ['title' => 'Monitoring', 'body' => '24/7 proactive threat monitoring.'],
                ],
            ],
            [
                'number' => '04',
                'title' => 'Your Rights',
                'items' => [
                    ['title' => 'Access', 'body' => 'Request a copy of your data.'],
                    ['title' => 'Correction', 'body' => 'Rectify inaccuracies in your data.'],
                    ['title' => 'Deletion', 'body' => 'Request the removal of your data.'],
                ],
            ],
        ],
        'privacy_support' => [
            'title' => 'Privacy Support',
            'description' => 'For inquiries regarding your data or to exercise your rights, contact our privacy desk.',
        ],
    ],
];
