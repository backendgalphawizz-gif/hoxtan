<?php

return [
    'provider' => env('KYC_PROVIDER', 'stub'),

    'surepass' => [
        'base_url' => env('SUREPASS_BASE_URL', 'https://kyc-api.surepass.app'),
        'token' => env('SUREPASS_TOKEN', ''),
        'pan_path' => env('SUREPASS_PAN_PATH', '/api/v1/pan/pan-comprehensive'),
        'pan_id_field' => env('SUREPASS_PAN_ID_FIELD', 'id_number'),
        'timeout' => (int) env('SUREPASS_TIMEOUT', 30),
    ],

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
