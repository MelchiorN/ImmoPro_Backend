<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Enregistre le FCM device token de l'utilisateur connecté.
 * L'app mobile appelle cet endpoint après login pour activer les push.
 *
 * POST /api/device-token
 * body: { "device_token": "fcm_token_string" }
 */
class DeviceTokenController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string|max:500',
        ]);

        $request->user()->update([
            'device_token' => $request->input('device_token'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token de notification enregistré.',
        ]);
    }

    /**
     * Supprime le token (logout / désactiver les push).
     * DELETE /api/device-token
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->update([
            'device_token' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token de notification supprimé.',
        ]);
    }
}
