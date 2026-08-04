<?php

return [
    'provider' => env('KYC_PROVIDER', 'stub'),

    'surepass' => [
        'base_url' => env('SUREPASS_BASE_URL', 'https://kyc-api.surepass.app'),
        'token' => env('SUREPASS_TOKEN', ''),
        'pan_path' => env('SUREPASS_PAN_PATH', '/api/v1/pan/pan-comprehensive'),
        'pan_id_field' => env('SUREPASS_PAN_ID_FIELD', 'id_number'),
        'bank_path' => env('SUREPASS_BANK_PATH', '/api/v1/bank-verification/'),
        'bank_account_field' => env('SUREPASS_BANK_ACCOUNT_FIELD', 'id_number'),
        'bank_ifsc_field' => env('SUREPASS_BANK_IFSC_FIELD', 'ifsc'),
        'bank_ifsc_details' => filter_var(env('SUREPASS_BANK_IFSC_DETAILS', true), FILTER_VALIDATE_BOOL),
        'digilocker_initialize_path' => env('SUREPASS_DIGILOCKER_INITIALIZE_PATH', '/api/v1/digilocker/initialize'),
        'digilocker_status_path' => env('SUREPASS_DIGILOCKER_STATUS_PATH', '/api/v1/digilocker/status'),
        'digilocker_download_aadhaar_path' => env('SUREPASS_DIGILOCKER_DOWNLOAD_AADHAAR_PATH', '/api/v1/digilocker/download-aadhaar'),
        'digilocker' => [
            'signup_flow' => filter_var(env('SUREPASS_DIGILOCKER_SIGNUP_FLOW', true), FILTER_VALIDATE_BOOL),
            'auth_type' => env('SUREPASS_DIGILOCKER_AUTH_TYPE', 'app'),
            'logo_url' => env('SUREPASS_DIGILOCKER_LOGO_URL'),
            'voice_assistant_lang' => env('SUREPASS_DIGILOCKER_VOICE_LANG', 'hi'),
            'voice_assistant' => filter_var(env('SUREPASS_DIGILOCKER_VOICE_ASSISTANT', true), FILTER_VALIDATE_BOOL),
            'retry_count' => (int) env('SUREPASS_DIGILOCKER_RETRY_COUNT', 3),
            'skip_main_screen' => filter_var(env('SUREPASS_DIGILOCKER_SKIP_MAIN_SCREEN', false), FILTER_VALIDATE_BOOL),
        ],
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
