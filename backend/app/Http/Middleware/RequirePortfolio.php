<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePortfolio
{
    public function handle(Request $request, Closure $next): Response
    {
        // Authentication runs first. Never invent a default/global portfolio.
        abort_unless($request->user()?->portfolio(), 404, 'No hay una cartera disponible para esta cuenta.');

        return $next($request);
    }
}
