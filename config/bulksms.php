<?php

return [

    'enabled' => (bool) env('BULKSMS_ENABLED', true),

    'base_url' => env('BULKSMS_BASE_URL', 'http://login.yourbulksms.com/api/sendhttp.php'),

    'authkey' => env('BULKSMS_AUTHKEY', ''),

    'sender' => env('BULKSMS_SENDER', ''),

    'route' => env('BULKSMS_ROUTE', '4'),

    'country' => env('BULKSMS_COUNTRY', '91'),

    /*
    | Default DLT template ID (register / forgot / fallback).
    */
    'dlt_te_id' => env('BULKSMS_DLT_TE_ID', ''),

    /*
    | Purpose-specific DLT template IDs.
    | login + resend-otp use templates.login
    */
    'templates' => [
        'login' => [
            'dlt_te_id' => env('BULKSMS_LOGIN_DLT_TE_ID', '1707178288608375575'),
            'message' => env(
                'BULKSMS_LOGIN_OTP_MESSAGE',
                env('BULKSMS_OTP_MESSAGE', 'Your OTP is {otp}. Do not share it with anyone.')
            ),
        ],
        'register' => [
            'dlt_te_id' => env('BULKSMS_REGISTER_DLT_TE_ID', env('BULKSMS_DLT_TE_ID', '')),
            'message' => env(
                'BULKSMS_REGISTER_OTP_MESSAGE',
                env('BULKSMS_OTP_MESSAGE', 'Your OTP is {otp}. Do not share it with anyone.')
            ),
        ],
        'forgot-mpin' => [
            'dlt_te_id' => env('BULKSMS_FORGOT_MPIN_DLT_TE_ID', env('BULKSMS_DLT_TE_ID', '')),
            'message' => env(
                'BULKSMS_FORGOT_MPIN_OTP_MESSAGE',
                env('BULKSMS_OTP_MESSAGE', 'Your OTP is {otp}. Do not share it with anyone.')
            ),
        ],
        'driver-login' => [
            'dlt_te_id' => env('BULKSMS_DRIVER_LOGIN_DLT_TE_ID', env('BULKSMS_DLT_TE_ID', '')),
            'message' => env(
                'BULKSMS_DRIVER_LOGIN_OTP_MESSAGE',
                env('BULKSMS_OTP_MESSAGE', 'Your OTP is {otp}. Do not share it with anyone.')
            ),
        ],
    ],

    /*
    | Default message body. Use {otp} placeholder.
    | Must match your DLT-approved template text.
    */
    'otp_message' => env(
        'BULKSMS_OTP_MESSAGE',
        'Your OTP is {otp}. Do not share it with anyone.'
    ),

    'timeout' => (int) env('BULKSMS_TIMEOUT', 15),

];
