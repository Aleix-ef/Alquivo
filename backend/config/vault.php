<?php

return [
    // Independent from APP_KEY. The file contains a JSON keyring, newest first.
    'key' => env('DOCUMENT_ENCRYPTION_KEY'),
    'key_file' => env('DOCUMENT_KEY_FILE', storage_path('app/keys/documents.json')),
];
