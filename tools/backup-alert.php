<?php

// Infrastructure notification only. Never include logs, secrets or user records.
require '/app/vendor/autoload.php';
$source = $argv[1] ?? '';
if (! in_array($source, ['alquivo-backup.service', 'alquivo-backup-watch.service', 'alquivo-backup-test.service'], true)) {
    exit(1);
}
$env = Dotenv\Dotenv::createArrayBacked('/run', 'production.env')->load();
$key = $env['RESEND_API_KEY'] ?? '';
$from = $env['MAIL_FROM_ADDRESS'] ?? '';
if ($key === '' || ! filter_var($from, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Backup notification configuration is missing.\n");
    exit(1);
}
$test = $source === 'alquivo-backup-test.service';
$payload = json_encode([
    'from' => 'Alquivo <'.$from.'>',
    'to' => ['soporte@alquivo.com'],
    'subject' => $test ? 'Prueba de alerta de copias de Alquivo' : 'Alquivo: revisar copias de seguridad',
    'text' => $test
        ? 'Prueba técnica de notificación de copias externas. No hay una avería real ni se ha enviado información de usuarios.'
        : 'El control '.$source.' ha fallado. Revisa el servicio y la última copia verificada en el VPS. Esta notificación no contiene datos de usuarios ni claves.',
], JSON_THROW_ON_ERROR);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Authorization: Bearer {$key}\r\nContent-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 20,
        'ignore_errors' => true,
        'follow_location' => 0,
    ],
    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
]);
$response = @file_get_contents('https://api.resend.com/emails', false, $context);
$status = $http_response_header[0] ?? '';
if ($response === false || ! preg_match('~^HTTP/\S+ 2\d\d\b~', $status)) {
    fwrite(STDERR, "Backup notification could not be delivered to the provider.\n");
    exit(1);
}
echo "Backup notification accepted by the email provider.\n";
