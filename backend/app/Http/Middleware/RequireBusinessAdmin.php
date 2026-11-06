<?php

namespace App\Http\Middleware;

use App\Domain\Administration\Services\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireBusinessAdmin
{
    public function handle(Request $request, Closure $next, string $mode = 'verified'): Response
    {
        $access = app(AdminAccess::class);
        abort_unless($access->allowed($request->user()) && $request->hasSession(), 403, 'No tienes autorización administrativa.');
        if ($mode !== 'challenge' && ! $access->verified($request)) {
            return response()->json([
                'message' => 'Verifica de nuevo tu segundo factor para entrar en administración.',
                'code' => 'admin_verification_required',
            ], 403)->header('Cache-Control', 'private, no-store');
        }

        return $next($request)->header('Cache-Control', 'private, no-store');
    }
}
