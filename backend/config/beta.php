<?php

return [
    'program_enabled' => (bool) env('BETA_PROGRAM_ENABLED', true),
    'billing_enabled' => (bool) env('BILLING_ENABLED', false),
    'fiscality_enabled' => (bool) env('FISCALITY_ENABLED', false),
    'assistant_validated' => (bool) env('ASSISTANT_VALIDATED', false),
];
