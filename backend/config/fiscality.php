<?php

return [
    // Each year needs its own reviewed rules. Do not infer future years from the last one.
    'years' => [2025],
    'rules_version' => 'es-common-2025.1',
    'review_status' => 'beta_pending_professional_review',
    'max_reports_per_year' => 100,
    'expense_categories' => [
        'interest' => 'Intereses y financiación',
        'repairs' => 'Reparación y conservación',
        'ibi' => 'IBI y tasas deducibles',
        'community' => 'Comunidad',
        'insurance' => 'Seguros',
        'management' => 'Gestión y servicios',
        'utilities' => 'Suministros del propietario',
    ],
    'sources' => [
        ['label' => 'Ley IRPF: artículos 22, 23, 85 y disposición transitoria 38', 'url' => 'https://www.boe.es/eli/es/l/2006/11/28/35/con'],
        ['label' => 'AEAT 2025: imputación temporal', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/irpf-2025/c04-rendimientos-capital-inmobiliario/imputacion-temporal-rendimientos-capital-inmobiliario.html'],
        ['label' => 'AEAT 2025: financiación, reparaciones y arrastres', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/irpf-2025/c04-rendimientos-capital-inmobiliario/gastos-deducibles/intereses-demas-gastos-financiacion-inmueble.html'],
        ['label' => 'AEAT 2025: amortización', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/irpf-2025/c04-rendimientos-capital-inmobiliario/gastos-deducibles/cantidades-destinadas-amortizacion.html'],
        ['label' => 'AEAT 2025: reducciones por vivienda', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/irpf-2025/c04-rendimientos-capital-inmobiliario/reducciones-rendimiento-neto/arrendamiento-inmuebles-destinados-vivienda.html'],
        ['label' => 'AEAT 2025: imputación inmobiliaria', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-ayuda-presentacion/irpf-2025/7-cumplimentacion-irpf/7_3-rendimientos-derivados-inmuebles/7_3_2-imputacion-rentas.html'],
    ],
];
