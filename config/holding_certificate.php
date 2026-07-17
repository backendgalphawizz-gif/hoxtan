<?php

return [
    /*
    | Proof-of-holdings certificate (issued on completed metal buy).
    | Layout matches industry-standard POH certificate format.
    */
    'prefix' => env('HOLDING_CERTIFICATE_PREFIX', 'HXT-POH'),

    'vault_audit_frequency' => 'Annual',

    'brand' => [
        'name' => env('HOLDING_CERT_BRAND_NAME', 'HOXTAN'),
        'tagline' => 'Digital Gold Provider',
        'logo' => 'images/certificates/hoxtan-brand.svg',
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

    'custody_note' => 'All {metal} purchased on {brand} is stored safely in an independent world-class bullion vault facility provided by {custodian_name} located at the registered office address mentioned below.',

    'trustee_note' => '{trustee_name} acts as an Independent Security Trustee (as designated by {brand}) and is responsible for ensuring that the {metal} in the vault is equal to the outstanding {brand} balances. This certificate is an official confirmation of {metal} holdings and is intended for use solely in this capacity.',

    'trustee' => [
        'title' => 'Trustee Administrator Details',
        'name' => env('HOLDING_CERT_TRUSTEE_NAME', 'Vistra Corporate Services (India) Private Limited'),
        'logo' => 'images/certificates/vistra-logo.svg',
        'registered_office_lines' => [
            env('HOLDING_CERT_TRUSTEE_ADDRESS_1', '13th Floor, \'Prestige Obelisk\', No. 3, Kasturba Road'),
            env('HOLDING_CERT_TRUSTEE_ADDRESS_2', 'Bengaluru - 560001, Karnataka, India'),
        ],
        'phone' => env('HOLDING_CERT_TRUSTEE_PHONE', '+91 80 4034 0200'),
        'cin' => env('HOLDING_CERT_TRUSTEE_CIN', 'U74140KA2007FTC044677'),
    ],

    'custodian' => [
        'title' => 'Custodian Details',
        'name' => env('HOLDING_CERT_CUSTODIAN_NAME', 'Brink\'s Arya Gold And Silver Refinery Private Limited'),
        'tagline' => 'Custodian Vault',
        'logo' => 'images/certificates/brinks-logo.svg',
        'bis_logo' => 'images/certificates/bis-logo.png',
        'registered_office_lines' => [
            env('HOLDING_CERT_CUSTODIAN_ADDRESS_1', '2nd Floor, Arya Gold Annex, Arya Gold Refinery Premises'),
            env('HOLDING_CERT_CUSTODIAN_ADDRESS_2', 'MIDC, Tarapur Industrial Area, Boisar, Maharashtra, India 421302'),
        ],
        'phone' => env('HOLDING_CERT_CUSTODIAN_PHONE', '+91 22 6662 9700'),
        'cin' => env('HOLDING_CERT_CUSTODIAN_CIN', 'U74999MH2011PTC221295'),
    ],
];
