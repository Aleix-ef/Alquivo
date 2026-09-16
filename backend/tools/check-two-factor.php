<?php

use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

// Local integration probe with a disposable account. Never run in production.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local')) {
    throw new RuntimeException('Local environment required');
}
$user = $portfolio = null;
$password = bin2hex(random_bytes(20));
$totp = new Google2FA;
$user = new User;
try {
    $user->forceFill(['name' => 'Disposable security probe', 'email' => 'probe-'.bin2hex(random_bytes(12)).'@example.invalid', 'password' => $password,
        'email_verified_at' => now(), 'two_factor_secret' => $totp->generateSecretKey(), 'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => [hash('sha256', 'local-recovery-probe')]])->save();
    $portfolio = Portfolio::create(['name' => 'Disposable security probe']);
    $portfolio->members()->attach($user, ['role' => 'owner']);
    $cookies = new CookieJar;
    $client = new Client(['base_uri' => 'http://web', 'cookies' => $cookies, 'http_errors' => false, 'allow_redirects' => false, 'timeout' => 15]);
    $request = function ($method, $path, $body = null) use ($client, $cookies) {
        $options = ['headers' => ['Accept' => 'application/json', 'Origin' => 'http://localhost:8080', 'Referer' => 'http://localhost:8080/login']];
        foreach ($cookies->toArray() as $cookie) {
            if ($cookie['Name'] === 'XSRF-TOKEN') {
                $options['headers']['X-XSRF-TOKEN'] = urldecode($cookie['Value']);
            }
        }
        if ($body !== null) {
            $options['json'] = $body;
        }

        return $client->request($method, $path, $options);
    };
    $expect = function ($response, $status) {
        if ($response->getStatusCode() !== $status) {
            throw new RuntimeException('Unexpected HTTP status: '.$response->getStatusCode().' (expected '.$status.')');
        }
    };
    $expect($request('GET', '/sanctum/csrf-cookie'), 204);
    $login = $request('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => $password]);
    $expect($login, 200);
    if (! (json_decode((string) $login->getBody(), true)['two_factor_required'] ?? false)) {
        throw new RuntimeException('Missing challenge');
    }
    $expect($request('GET', '/api/v1/auth/me'), 401);
    $code = $totp->getCurrentOtp($user->two_factor_secret);
    $expect($request('POST', '/api/v1/auth/two-factor', ['code' => $code]), 200);
    $expect($request('GET', '/api/v1/auth/me'), 200);
    $expect($request('POST', '/api/v1/auth/logout'), 204);
    $expect($request('GET', '/sanctum/csrf-cookie'), 204);
    $expect($request('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => $password]), 200);
    $expect($request('POST', '/api/v1/auth/two-factor', ['code' => $code]), 422);
    $expect($request('POST', '/api/v1/auth/two-factor', ['code' => 'local-recovery-probe']), 200);
    $expect($request('POST', '/api/v1/auth/logout'), 204);
    echo "PASS: real HTTP cookies/CSRF, password-only denied, TOTP accepted, replay denied, recovery accepted.\n";
} finally {
    if ($user?->exists) {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $portfolio?->delete();
        $user->delete();
    }
}
