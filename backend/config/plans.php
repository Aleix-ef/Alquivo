<?php

return [
    'free' => [
        'name' => 'Gratuito', 'price_monthly' => 0, 'price_yearly' => 0, 'property_limit' => 1,
        'storage_limit_bytes' => 52428800,
        'prices' => ['monthly' => null, 'yearly' => null],
        'features' => ['Dashboard patrimonial', 'Un inmueble', 'Gestión básica de alquileres', '50 MB de documentos'],
        'commercial' => false,
    ],
    'starter' => [
        'name' => 'Propietario', 'price_monthly' => 9.99, 'price_yearly' => 90, 'property_limit' => 5,
        'storage_limit_bytes' => 262144000,
        'prices' => ['monthly' => env('STRIPE_PRICE_STARTER_MONTHLY'), 'yearly' => env('STRIPE_PRICE_STARTER_YEARLY')],
        'features' => ['Dashboard patrimonial', 'Alquileres y cobros', 'Documentos y calendario', 'Informes y CSV'],
        'commercial' => true,
    ],
    'investor' => [
        'name' => 'Inversor', 'price_monthly' => 19.99, 'price_yearly' => 190, 'property_limit' => 20,
        'storage_limit_bytes' => 2147483648,
        'prices' => ['monthly' => env('STRIPE_PRICE_INVESTOR_MONTHLY'), 'yearly' => env('STRIPE_PRICE_INVESTOR_YEARLY')],
        'features' => ['Todo en Propietario', 'Hasta 20 inmuebles', '2 GB de documentos', 'Soporte prioritario'],
        'commercial' => true,
    ],
];
