<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de contrôle de rôle.
 *
 * Usage dans les routes :
 *   ->middleware(['auth:sanctum', 'role:admin'])
 *   ->middleware(['auth:sanctum', 'role:agent'])
 *   ->middleware(['auth:sanctum', 'role:admin,agent'])
 *   ->middleware(['auth:sanctum', 'role:client'])
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié.',
            ], 401);
        }

        if (! in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Rôle requis : ' . implode(' ou ', $roles) . '.',
            ], 403);
        }

        // Vérifie aussi que le compte est actif
        if (in_array($user->status, ['blocked', 'suspended'])) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est ' . $user->status . '.',
            ], 403);
        }

        return $next($request);
    }
}
