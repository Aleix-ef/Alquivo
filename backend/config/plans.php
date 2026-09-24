<?php

return [
    'beta' => [
        'name' => 'Beta gratuita', 'price_monthly' => 0, 'price_yearly' => null, 'property_limit' => 10,
        'storage_limit_bytes' => 1073741824,
        'prices' => ['monthly' => null, 'yearly' => null],
        'entitlements' => ['fiscal_reports'],
        'features' => ['Hasta 10 inmuebles', '1 GB de documentos y fotos', 'Inquilinos, contratos, cobros y gastos', 'Incidencias, calendario e informes', 'Exportación de datos y soporte', 'Asistente con hasta 20 consultas al mes', 'Fiscalidad beta'],
        'commercial' => false,
    ],
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
