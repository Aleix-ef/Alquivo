<?php

declare(strict_types=1);

// Generate NEW installation secrets only. Never print them or replace a file.
if (PHP_SAPI !== 'cli' || $argc !== 3) {
    fwrite(STDERR, "Usage: php init-production-env.php TEMPLATE TARGET\n");
    exit(1);
}

[$script, $template, $target] = $argv;
if (file_exists($target) || is_link($target)) {
    fwrite(STDOUT, "Existing production environment preserved; no keys changed.\n");
    exit(0);
}

$contents = file_get_contents($template);
if ($contents === false) {
    fwrite(STDERR, "Cannot read the production template.\n");
    exit(1);
}

$secrets = [
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'DB_PASSWORD' => bin2hex(random_bytes(32)),
    'DATABASE_ADMIN_PASSWORD' => bin2hex(random_bytes(32)),
];
foreach ($secrets as $name => $value) {
    $contents = preg_replace('/^'.preg_quote($name, '/').'=[\t ]*$/m', $name.'='.$value, $contents, -1, $count);
    if ($contents === null || $count !== 1) {
        fwrite(STDERR, "Template must have exactly one empty placeholder for {$name}.\n");
        exit(1);
    }
}

umask(0077);
$handle = fopen($target, 'x');
if ($handle === false) {
    fwrite(STDERR, "Cannot create production environment exclusively.\n");
    exit(1);
}
if (fwrite($handle, $contents) !== strlen($contents)) {
    fclose($handle);
    unlink($target);
    fwrite(STDERR, "Cannot save production environment.\n");
    exit(1);
}
fclose($handle);
fwrite(STDOUT, "New production environment created with private permissions; provider keys still need configuration.\n");
