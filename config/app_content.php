<?php

return [
    'app_name' => 'HOXTAN',

    'website_support' => [
        'email' => 'support@hoxtandigigold.com',
        'toll_free' => '18005693934',
    ],

    /*
    | Website pages managed from Admin → CMS → Static Pages.
    | Slug must match the static page record to appear on the website.
    */
    'website_pages' => [
        ['key' => 'about', 'slug' => 'about-us', 'label' => 'About Us'],
        ['key' => 'terms', 'slug' => 'terms-and-conditions', 'label' => 'Terms & Conditions'],
        ['key' => 'privacy', 'slug' => 'privacy-policy', 'label' => 'Privacy Policy'],
        ['key' => 'user_terms', 'slug' => 'user-terms-and-conditions', 'label' => 'User Terms & Conditions'],
        ['key' => 'user_privacy', 'slug' => 'user-privacy-policy', 'label' => 'User Privacy Policy'],
        ['key' => 'driver_terms', 'slug' => 'driver-terms-and-conditions', 'label' => 'Driver Terms & Conditions'],
        ['key' => 'driver_privacy', 'slug' => 'driver-privacy-policy', 'label' => 'Driver Privacy Policy'],
        ['key' => 'delete_account', 'slug' => 'delete-account', 'label' => 'Delete Account'],
    ],

    /*
    | Public URLs for Google Play Console (use APP_URL in production).
    */
    'play_store' => [
        'privacy_policy_url' => '/privacy-policy',
        'delete_account_url' => '/delete-account',
        'privacy_policy_embed_url' => '/embed/privacy-policy',
        'delete_account_embed_url' => '/embed/delete-account',
    ],

    'user_play_store' => [
        'privacy_policy_url' => '/user-privacy-policy',
        'terms_url' => '/user-terms-and-conditions',
        'privacy_policy_embed_url' => '/embed/user-privacy-policy',
        'terms_embed_url' => '/embed/user-terms-and-conditions',
    ],

    'driver_play_store' => [
        'privacy_policy_url' => '/driver-privacy-policy',
        'terms_url' => '/driver-terms-and-conditions',
        'privacy_policy_embed_url' => '/embed/driver-privacy-policy',
        'terms_embed_url' => '/embed/driver-terms-and-conditions',
    ],

    /*
    | Legal pages for user app (CMS slugs). Falls back to legacy slugs when needed.
    */
    'user_privacy' => [
        'slug' => 'user-privacy-policy',
        'fallback_slug' => 'privacy-policy',
        'title' => 'Privacy Policy',
        'url_path' => '/user-privacy-policy',
        'embed_url_path' => '/embed/user-privacy-policy',
        'tagline' => 'Your trust is our most valuable asset.',
        'privacy_support_email' => 'privacy@hoxtan.com',
    ],

    'user_terms' => [
        'slug' => 'user-terms-and-conditions',
        'fallback_slug' => 'terms-and-conditions',
        'title' => 'Terms & Conditions',
        'url_path' => '/user-terms-and-conditions',
        'embed_url_path' => '/embed/user-terms-and-conditions',
        'version' => 'V.4.02',
        'acceptance_label' => 'I have read and agree to the Terms & Conditions.',
        'accept_button_label' => 'Accept & Continue',
    ],

    'driver_privacy' => [
        'slug' => 'driver-privacy-policy',
        'title' => 'Driver Privacy Policy',
        'url_path' => '/driver-privacy-policy',
        'embed_url_path' => '/embed/driver-privacy-policy',
        'tagline' => 'How we protect driver data on the HOXTAN Driver app.',
    ],

    'driver_terms' => [
        'slug' => 'driver-terms-and-conditions',
        'title' => 'Driver Terms & Conditions',
        'url_path' => '/driver-terms-and-conditions',
        'embed_url_path' => '/embed/driver-terms-and-conditions',
        'version' => 'V.1.0',
        'acceptance_label' => 'I have read and agree to the Driver Terms & Conditions.',
        'accept_button_label' => 'Accept & Continue',
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

    'delete_account' => [
        'slug' => 'delete-account',
        'title' => 'Delete Your Account',
        'url_path' => '/delete-account',
        'embed_url_path' => '/embed/delete-account',
        'support_email' => 'support@hoxtandigigold.com',
        'close_account' => [
            'method' => 'POST',
            'path' => '/api/v1/profile/close-account',
            'requires_mpin' => true,
            'request' => [
                'mpin' => '4-digit M-PIN',
            ],
        ],
        'steps' => [
            'Open the app and sign in.',
            'Go to Profile.',
            'Tap Close Account / Delete Account.',
            'Enter your M-PIN to confirm.',
        ],
    ],

    'landing_features' => [
        [
            'icon' => '💰',
            'title' => 'Buy Gold & Silver',
            'text' => 'Purchase digital gold and silver at live market rates with transparent GST pricing and instant confirmation.',
            'details' => [
                'Buy 24K gold and 999 fine silver at real-time market-linked rates.',
                'Transparent GST breakdown shown before every purchase.',
                'Instant digital allocation — no waiting for settlement.',
                'Start with small amounts and build holdings over time.',
                'Download purchase invoices anytime from your account.',
            ],
        ],
        [
            'icon' => '📈',
            'title' => 'SIG Auto-Invest',
            'text' => 'Set up Systematic Investment in Gold — daily, weekly, or monthly — and grow your holdings automatically.',
            'details' => [
                'Choose daily, weekly, or monthly investment frequency.',
                'Fixed amount auto-invested at prevailing gold rates.',
                'Pause, resume, or stop your plan anytime from the app.',
                'Track every installment and accumulated grams in one place.',
                'Ideal for long-term wealth building without timing the market.',
            ],
        ],
        [
            'icon' => '💎',
            'title' => 'Premium Jewellery',
            'text' => 'Shop hallmarked gold & silver jewellery with live pricing, making charges, and doorstep delivery.',
            'details' => [
                'Browse hallmarked gold and silver jewellery collections.',
                'Live metal rates plus transparent making charge breakdown.',
                'Secure checkout with saved delivery addresses.',
                'Order tracking from confirmation to doorstep delivery.',
                'Quality craftsmanship backed by verified purity standards.',
            ],
        ],
        [
            'icon' => '🔒',
            'title' => 'Secure Vault',
            'text' => 'Your holdings are stored in allocated, segregated vaults with 256-bit encryption and full insurance.',
            'details' => [
                'Allocated and segregated storage for your digital holdings.',
                '256-bit encryption protects your account and transactions.',
                'Insurance coverage on stored precious metal assets.',
                'Regular audits and compliance with industry standards.',
                'Peace of mind knowing your wealth is physically secured.',
            ],
        ],
        [
            'icon' => '🔄',
            'title' => 'Instant Sell & Redeem',
            'text' => 'Sell your digital gold or silver anytime at live rates, or redeem physical bullion to your doorstep.',
            'details' => [
                'Sell digital gold or silver instantly at live market rates.',
                'Proceeds credited to your wallet without lengthy delays.',
                'Redeem physical bullion with doorstep delivery options.',
                'Real-time rate lock at the time of your transaction.',
                'Full transaction history available in My Orders.',
            ],
        ],
        [
            'icon' => '✅',
            'title' => 'KYC Verified',
            'text' => 'Complete Aadhaar, PAN, and face verification for a fully compliant and secure trading experience.',
            'details' => [
                'Quick Aadhaar and PAN verification via secure OTP flow.',
                'Face match verification for added account security.',
                'Bank account linking for seamless withdrawals.',
                'Fully compliant with regulatory KYC requirements.',
                'Verified accounts unlock the complete platform experience.',
            ],
        ],
    ],
];
