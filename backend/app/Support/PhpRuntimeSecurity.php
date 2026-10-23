<?php

namespace App\Support;

/** Official PHP image baselines reviewed on 2026-10-23; not vendor backport detection. */
class PhpRuntimeSecurity
{
    public function failures(): array
    {
        return self::inspect(PHP_VERSION, ini_get('session.upload_progress.enabled'));
    }

    public static function inspect(string $version, string|false $uploadProgress): array
    {
        // New branches need an explicit runtime review, rather than assuming an RC is safe.
        $branch = implode('.', array_slice(explode('.', $version), 0, 2));
        $minimum = ['8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'][$branch] ?? null;
        $failures = [];
        if ($minimum === null || version_compare($version, $minimum, '<')) {
            $failures[] = 'Runtime PHP no validado: utiliza una rama soportada con los parches críticos del 21/10/2026.';
        }
        if (filter_var($uploadProgress, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) !== false) {
            $failures[] = 'Desactiva session.upload_progress.enabled en php.ini y reinicia todos los workers PHP.';
        }

        return $failures;
    }
}
