<?php

// Conservative guard: prints filenames and rule names, never credential matches.
chdir(__DIR__.'/..');
$list = shell_exec('git ls-files -z --cached --others --exclude-standard');
if ($list === null) exit(2);
$failed = false;
$patterns = [
    'Stripe credential' => '/(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{20,}|whsec_[A-Za-z0-9]{20,}/',
    'OpenAI credential' => '/sk-proj-[A-Za-z0-9_-]{30,}/',
    'GitHub credential' => '/gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,}/',
    'AWS access key' => '/AKIA[0-9A-Z]{16}/',
    'Private key' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
];
foreach (array_unique(explode("\0", $list)) as $file) {
    if (! is_file($file)) continue;
    $name = basename($file);
    $publicDevelopmentUrl = $file === 'frontend/.env.development' && trim(file_get_contents($file)) === 'VITE_API_URL=http://127.0.0.1:8100/api/v1';
    if ((str_starts_with($name, '.env') && ! str_ends_with($name, '.example') && ! $publicDevelopmentUrl) || preg_match('~^(secrets|backups)/~', $file) || preg_match('/\.(dump|sqlite|pem|key)$/', $file)) {
        fwrite(STDERR, "Private artifact must not be committed: {$file}\n");
        $failed = true;
        continue;
    }
    if (preg_match('/\.(png|webp|jpg|jpeg|woff2|pdf)$/i', $file)) continue;
    $content = file_get_contents($file);
    foreach ($patterns as $rule => $pattern) {
        if (preg_match($pattern, $content)) {
            fwrite(STDERR, "Possible {$rule} in {$file} (value redacted).\n");
            $failed = true;
        }
    }
}
if ($failed) exit(1);
echo "PASS: no known credential patterns or private artifacts in publishable files. This is not an exhaustive secret audit.\n";
