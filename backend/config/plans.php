<?php

return [
    'free' => [
        'name' => 'Gratuito', 'price_monthly' => 0, 'price_yearly' => 0, 'property_limit' => 1,
        'storage_limit_bytes' => 52428800,
        'prices' => ['monthly' => null, 'yearly' => null],
        'features' => ['Dashboard, alquileres y finanzas', 'Un inmueble', '50 MB de documentos', 'Asistente con 5 consultas al mes'],
        'commercial' => false,
    ],
    'founder' => [
        'entitlements' => ['fiscal_reports'],
        'name' => 'Plan Fundador', 'price_monthly' => 6.99, 'price_yearly' => null, 'property_limit' => 20,
        'storage_limit_bytes' => 2147483648,
        'prices' => ['monthly' => env('STRIPE_PRICE_FOUNDER_MONTHLY'), 'yearly' => null],
        'features' => ['Hasta 20 inmuebles', '2 GB de documentos', 'Informes y exportación de datos', 'Asistente con 50 consultas al mes', 'Fiscalidad beta'],
        'commercial' => true,
    ],
];
