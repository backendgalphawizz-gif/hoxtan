<?php

return [
    /*
    | Place your Firebase service account JSON at:
    |   storage/app/firebase/service-account.json
    | (this folder is gitignored)
    |
    | Or set FIREBASE_CREDENTIALS to an absolute path.
    */
    'credentials' => env(
        'FIREBASE_CREDENTIALS',
        storage_path('app/firebase/service-account.json'),
    ),

    'project_id' => env('FIREBASE_PROJECT_ID', 'hoxtan-1cd97'),

    'enabled' => env('FIREBASE_ENABLED', true),
];
