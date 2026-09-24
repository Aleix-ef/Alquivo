<?php

use App\Domain\Assistant\Documents\DocumentDemoFixtures;

// Deterministic synthetic artifacts for visual QA. No app data or provider access.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$fixtures = new DocumentDemoFixtures;
foreach (['invoice', 'contract'] as $kind) {
    $path = sys_get_temp_dir().'/alquivo-document-demo-'.$kind.'.pdf';
    file_put_contents($path, $fixtures->pdf($kind));
    echo $path.PHP_EOL;
}
