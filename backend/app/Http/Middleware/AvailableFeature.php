<?php

namespace App\Http\Middleware;

use App\Support\ProductFeatures;
use Closure;
use Illuminate\Http\Request;

final class AvailableFeature
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        $features = app(ProductFeatures::class);
        abort_unless(match ($feature) {
            'fiscality' => $features->fiscality(), 'assistant' => config('beta.assistant_validated') && config('assistant.enabled'), default => false
        }, 404);

        return $next($request);
    }
}
