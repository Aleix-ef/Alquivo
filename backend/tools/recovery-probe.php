<?php

use App\Domain\Documents\Services\PrivateFileVault;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;

// Synthetic, non-personal canary: verifies both recovery keys even for an empty portfolio.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$vault = $app->make(PrivateFileVault::class);
$label = 'Alquivo recovery probe v1';
if ($argc === 1) {
    echo $vault->encrypt('recovery-probe', Crypt::encryptString($label));
    exit(0);
}
$value = Crypt::decryptString($vault->decrypt('recovery-probe', file_get_contents($argv[1])));
if (! hash_equals($label, $value)) {
    throw new RuntimeException('Recovery key verification failed');
}
echo "Both application and document recovery keys verified.\n";
