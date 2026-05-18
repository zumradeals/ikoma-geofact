<?php

return [
    'token'    => env('WIALON_TOKEN'),
    'base_url' => env('WIALON_BASE_URL', 'https://hosting.wialon.com'),
    'interval' => (int) env('WIALON_SYNC_INTERVAL', 30), // secondes
];
