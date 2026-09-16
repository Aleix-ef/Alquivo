<?php

return [
    'uploads_scan' => (bool) env('UPLOADS_SCAN_ENABLED', false),
    'clamav_host' => env('CLAMAV_HOST', 'clamav'),
    'clamav_port' => (int) env('CLAMAV_PORT', 3310),
];
