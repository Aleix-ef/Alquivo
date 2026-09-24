<?php

return [
    // Intentionally no live mode. Removing this boundary requires real evaluations and a privacy review.
    'enabled' => true,
    'notice_version' => 'documents-simulation-2026-09-23',
    'schema_version' => 1,
    'max_bytes' => 5 * 1024 * 1024,
    'max_pages' => 10,
    'max_image_pixels' => 20000000,
    'timeout_seconds' => 45,
    'max_output_tokens' => 4096,
    'budget_nano_usd' => 100000000,
    'retention_days' => 30,
    'max_attempts' => 3,
    'mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
];
