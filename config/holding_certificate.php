<?php

return [
    /*
    | Proof-of-holdings certificate (issued on completed metal buy).
    | Number format mirrors VIS-POH-YYMMDD-######## → HXT-POH-YYMMDD-########
    */
    'prefix' => env('HOLDING_CERTIFICATE_PREFIX', 'HXT-POH'),

    'vault_audit_frequency' => 'Annual',

    'brand' => [
        'name' => env('HOLDING_CERT_BRAND_NAME', 'HOXTAN'),
        'tagline' => env('HOLDING_CERT_BRAND_TAGLINE', 'Digital Gold Provider'),
        'logo' => 'images/hoxtan-logo.png',
        'icon' => 'images/hoxtan-icon.png',
    ],

    'gold' => [
        'purity' => '24K',
        'provider_label' => 'digital gold',
        'holding_label' => 'Gold Holding',
    ],

    'silver' => [
        'purity' => '999',
        'provider_label' => 'digital silver',
        'holding_label' => 'Silver Holding',
    ],

    'custody_note' => 'All metal purchased on HOXTAN is stored safely in an independent world-class bullion vault facility provided by the custodian named below.',

    'trustee_note' => 'The trustee administrator named below acts as an independent trustee responsible for protecting your interest and cross-verifies the details of the Auditor\'s physical metal report with daily Custodian reports as to metal balance in the vault. This certificate serves as an official confirmation of metal holdings and is intended for use solely in this capacity.',

    'trustee' => [
        'title' => 'Trustee Administrator Details',
        'name' => env('HOLDING_CERT_TRUSTEE_NAME', 'HOXTAN Trustee Services'),
        'logo' => null,
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
        'tagline' => 'Custodian Vault',
        'logo' => null,
        'registered_office_lines' => [
            env('HOLDING_CERT_CUSTODIAN_ADDRESS_1', 'Registered Office'),
            env('HOLDING_CERT_CUSTODIAN_ADDRESS_2', 'India'),
        ],
        'phone' => env('HOLDING_CERT_CUSTODIAN_PHONE', ''),
        'cin' => env('HOLDING_CERT_CUSTODIAN_CIN', ''),
    ],
];
