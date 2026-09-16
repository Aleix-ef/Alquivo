<?php

namespace App\Http\Middleware;

use App\Support\ProductionReadiness;
use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->isProduction() && app(ProductionReadiness::class)->failures()) {
            return response()->json(['message' => 'Servicio temporalmente no disponible.'], 503, ['Cache-Control' => 'no-store']);
        }
        // Cashier otherwise skips signature verification when no secret is configured.
        if ($request->is(trim(config('cashier.path', 'stripe'), '/').'/webhook') && ! filled(config('cashier.webhook.secret'))) {
            return response()->json(['message' => 'Facturación no disponible.'], 503);
        }

        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        // Keep same-origin browser GETs compatible with Sanctum cookie sessions.
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Cache-Control', 'no-store, private');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
