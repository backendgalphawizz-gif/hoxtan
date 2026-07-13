<?php

return [
    'broadcast_channel' => 'metal-rates',
    'broadcast_event' => 'rates.updated',

    // How often the scheduler pushes DB rates to the WebSocket (does not call Metals-API).
    'broadcast_interval_seconds' => 60,

    // Emergency fallback only when Metals-API is unavailable (not editable in admin).
    'fallback_rates' => [
        'gold' => 7250.0,
        'silver' => 85.5,
    ],

    // Cache Metals-API responses after sync (sync itself is scheduled 3×/day).
    'live_cache_seconds' => 28800,
];
