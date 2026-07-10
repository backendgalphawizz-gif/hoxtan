<?php

return [
    'broadcast_channel' => 'metal-rates',
    'broadcast_event' => 'rates.updated',

    // Emergency fallback only when Metals-API is unavailable (not editable in admin).
    'fallback_rates' => [
        'gold' => 7250.0,
        'silver' => 85.5,
    ],

    // Cache live Metals-API responses to avoid hitting the API on every request.
    'live_cache_seconds' => 60,
];
