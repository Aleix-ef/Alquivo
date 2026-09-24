<?php

namespace App\Support;

use App\Domain\Documents\Services\PrivateFileVault;
use Illuminate\Encryption\Encrypter;

class ProductionReadiness
{
    public function failures(): array
    {
        $https = fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && ! in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1', 'example.com', 'alquivo.example'], true);
        $connection = config('database.connections.'.config('database.default'), []);
        try {
            $fileKey = app(PrivateFileVault::class)->encrypter()->getKey();
            $appKey = str_starts_with((string) config('app.key'), 'base64:') ? base64_decode(substr(config('app.key'), 7), true) : config('app.key');
            $vaultReady = is_string($appKey) && ! hash_equals($appKey, $fileKey);
        } catch (\Throwable) {
            $vaultReady = false;
        }
        $failures = [];
        $checks = [
            'APP_ENV debe ser production.' => app()->isProduction(),
            'APP_DEBUG debe estar desactivado.' => config('app.debug') === false,
            'Configura y respalda la clave independiente de documentos (vault:key).' => $vaultReady,
            'APP_KEY debe ser una clave de cifrado válida.' => Encrypter::supported(str_starts_with((string) config('app.key'), 'base64:') ? (base64_decode(substr(config('app.key'), 7), true) ?: '') : (string) config('app.key'), config('app.cipher')),
            'APP_URL debe ser el dominio HTTPS real.' => $https(config('app.url')),
            'FRONTEND_URL debe ser el dominio HTTPS real.' => $https(config('services.frontend_url')),
            'Las cookies deben usar Secure, HttpOnly y SameSite=Lax/Strict.' => config('session.secure') === true && config('session.http_only') === true && in_array(config('session.same_site'), ['lax', 'strict'], true),
            'Se requieren sesiones de base de datos cifradas.' => config('session.driver') === 'database' && config('session.encrypt') === true,
            'La caché y la cola deben ser persistentes y compartidas.' => in_array(config('cache.default'), ['database', 'redis'], true) && in_array(config('queue.default'), ['database', 'redis'], true),
            'Configura MySQL/PostgreSQL con una contraseña larga y propia.' => in_array(config('database.default'), ['mysql', 'pgsql'], true) && strlen((string) ($connection['password'] ?? '')) >= 20 && ! str_contains((string) ($connection['password'] ?? ''), 'change-me'),
            'Configura un proveedor de correo real.' => ! in_array(config('mail.default'), ['log', 'array', null], true),
            'Configura la dirección real de envío de correo.' => filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) && ! str_ends_with((string) config('mail.from.address'), '@example.com'),
            'Configura un correo de soporte válido.' => (bool) filter_var(config('support.email'), FILTER_VALIDATE_EMAIL),
            'CORS debe contener exclusivamente orígenes HTTPS concretos.' => count(config('cors.allowed_origins', [])) > 0 && collect(config('cors.allowed_origins'))->every($https) && config('cors.allowed_origins_patterns', []) === [],
            'Activa el análisis antivirus de archivos.' => config('security.uploads_scan') === true,
            'TRUSTED_PROXIES no puede confiar en cualquier origen.' => array_intersect(['*', '**', 'REMOTE_ADDR'], config('trustedproxy.proxies', [])) === [],
        ];
        if (config('mail.default') === 'smtp') {
            $checks['Configura el host SMTP real con conexión TLS.'] = filled(config('mail.mailers.smtp.host'))
                && ! in_array(config('mail.mailers.smtp.host'), ['localhost', '127.0.0.1', 'mailpit'], true)
                && in_array((int) config('mail.mailers.smtp.port'), [465, 587], true);
        }
        $host = parse_url((string) config('services.frontend_url'), PHP_URL_HOST);
        $port = parse_url((string) config('services.frontend_url'), PHP_URL_PORT);
        $checks['SANCTUM_STATEFUL_DOMAINS debe incluir el dominio público.'] = in_array($host.($port ? ':'.$port : ''), config('sanctum.stateful', []), true);
        if (filled(config('cashier.secret'))) {
            $checks['Configura STRIPE_WEBHOOK_SECRET para validar eventos.'] = str_starts_with((string) config('cashier.webhook.secret'), 'whsec_');
        }
        if (app(ProductFeatures::class)->billing()) {
            $checks['Configura Stripe antes de activar la contratación.'] = filled(config('cashier.secret'));
            $checks['Configura el precio mensual del Plan Fundador en Stripe.'] = str_starts_with((string) config('plans.founder.prices.monthly'), 'price_');
        }
        if (config('beta.assistant_validated') && config('assistant.enabled')) {
            $checks['Configura la clave del asistente o desactívalo.'] = filled(config('services.openai.key'));
            $checks['Usa el endpoint HTTPS oficial del proveedor de IA.'] = in_array(rtrim(config('assistant.base_url'), '/'), ['https://api.openai.com/v1', 'https://eu.api.openai.com/v1'], true);
        }
        foreach ($checks as $message => $passed) {
            if (! $passed) {
                $failures[] = $message;
            }
        }

        return $failures;
    }
}
