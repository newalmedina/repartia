<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas de la API para la aplicación móvil:
 * solo usuarios activos con el permiso "Acceso a la aplicación móvil".
 *   Route::middleware(['auth:sanctum', 'mobile.app'])->...
 */
class EnsureMobileAppAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->canUseMobileApp()) {
            return response()->json([
                'message' => 'No tienes acceso a la aplicación móvil.',
            ], 403);
        }

        return $next($request);
    }
}
