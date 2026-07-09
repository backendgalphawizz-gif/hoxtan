<?php

return [
    'provider' => env('KYC_PROVIDER', 'stub'),

    'title' => 'Identity Vault',

    'steps' => [
        'pan' => [
            'key' => 'pan',
            'label' => 'PAN Verification',
            'description' => 'Verify your PAN card details.',
            'provider_label' => 'Income Tax Department',
        ],
        'aadhaar' => [
            'key' => 'aadhaar',
            'label' => 'Aadhaar Verification',
            'description' => 'Verify your Aadhaar via UIDAI secured OTP.',
            'provider_label' => 'UIDAI Secured',
        ],
        'face' => [
            'key' => 'face',
            'label' => 'Face Verification',
            'description' => 'Liveness and visual detection scan.',
            'provider_label' => 'Biometric Check',
        ],
        'bank' => [
            'key' => 'bank',
            'label' => 'Bank Connectivity',
            'description' => 'Link your bank account for liquidations and transactions.',
            'provider_label' => 'Bank Account Verification',
        ],
    ],

    'step_statuses' => [
        'action_required' => 'Action Required',
        'pending' => 'Pending',
        'otp_sent' => 'OTP Sent',
        'submitted' => 'Submitted',
        'verified' => 'Verified',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    'face_requirements' => [
        ['key' => 'lighting', 'label' => 'Bright Lighting'],
        ['key' => 'no_accessories', 'label' => 'No Accessories'],
        ['key' => 'center_face', 'label' => 'Center Face'],
    ],

    'user_kyc_statuses' => [
        'pending' => 'Pending KYC',
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'approved' => 'Completed',
        'rejected' => 'Rejected',
    ],
];
