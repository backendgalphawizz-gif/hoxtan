<?php

return [
    /*
    | Proof-of-holdings certificate (issued on completed metal buy).
    | Number format mirrors VIS-POH-YYMMDD-######## → HXT-POH-YYMMDD-########
    */
    'prefix' => env('HOLDING_CERTIFICATE_PREFIX', 'HXT-POH'),

    'vault_audit_frequency' => 'Annual',

    'digital_provider' => 'Custodian Vault',

    'gold' => [
        'purity' => '24K',
        'provider_label' => 'digital gold',
    ],

    'silver' => [
        'purity' => '999',
        'provider_label' => 'digital silver',
    ],

    'custody_note' => 'This certificate confirms continued secure custody of your metal in the vault facility.',

    'trustee' => [
        'title' => 'Trustee Administrator Details',
        'name' => env('HOLDING_CERT_TRUSTEE_NAME', 'HOXTAN Trustee Services'),
        'registered_office_lines' => [
            env('HOLDING_CERT_TRUSTEE_ADDRESS_1', 'Registered Office'),
            env('HOLDING_CERT_TRUSTEE_ADDRESS_2', 'India'),
        ],
        'phone' => env('HOLDING_CERT_TRUSTEE_PHONE', ''),
        'cin' => env('HOLDING_CERT_TRUSTEE_CIN', ''),
    ],

    'custodian' => [
        'title' => 'Custodian Details',
        'name' => env('HOLDING_CERT_CUSTODIAN_NAME', 'HOXTAN Vault Custodian'),
        'registered_office_lines' => [
            env('HOLDING_CERT_CUSTODIAN_ADDRESS_1', 'Registered Office'),
            env('HOLDING_CERT_CUSTODIAN_ADDRESS_2', 'India'),
        ],
        'phone' => env('HOLDING_CERT_CUSTODIAN_PHONE', ''),
        'cin' => env('HOLDING_CERT_CUSTODIAN_CIN', ''),
    ],
];
