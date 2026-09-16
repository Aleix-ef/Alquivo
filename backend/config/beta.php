<?php

return [
    'billing_enabled' => (bool) env('BILLING_ENABLED', false),
    'fiscality_enabled' => (bool) env('FISCALITY_ENABLED', false),
    'assistant_validated' => (bool) env('ASSISTANT_VALIDATED', false),
];
