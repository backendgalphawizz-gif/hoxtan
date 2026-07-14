<?php

return [

    'enabled' => (bool) env('BULKSMS_ENABLED', true),

    'base_url' => env('BULKSMS_BASE_URL', 'http://login.yourbulksms.com/api/sendhttp.php'),

    'authkey' => env('BULKSMS_AUTHKEY', ''),

    'sender' => env('BULKSMS_SENDER', 'ABCDEF'),

    'route' => env('BULKSMS_ROUTE', '2'),

    'country' => env('BULKSMS_COUNTRY', '0'),

    /*
    | DLT template ID from your SMS provider approval.
    */
    'dlt_te_id' => env('BULKSMS_DLT_TE_ID', ''),

    /*
    | Message body. Use {otp} placeholder.
    | Must match your DLT-approved template text.
    */
    'otp_message' => env(
        'BULKSMS_OTP_MESSAGE',
        'Your OTP is {otp}. Do not share it with anyone.'
    ),

    'timeout' => (int) env('BULKSMS_TIMEOUT', 15),

];
