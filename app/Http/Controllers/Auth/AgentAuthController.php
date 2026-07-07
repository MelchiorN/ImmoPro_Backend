<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\HistoriqueConnexion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AgentAuthController extends Controller
{
    /**
     * POST /api/agent/login
     * Authentifie un agent par email + mot de passe.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])
                    ->where('role', 'agent')
                    ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email ou mot de passe incorrect.',
            ]);
        }

        if ($user->status === 'blocked') {
            return response()->json([
                'message' => 'Votre compte a été bloqué. Contactez l\'administrateur.',
            ], 403);
        }

        if ($user->status === 'suspended') {
            return response()->json([
                'message' => 'Votre compte est temporairement suspendu.',
            ], 403);
        }

        // Historique de connexion
        HistoriqueConnexion::create([
            'user_id'      => $user->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'device_type'  => 'web',
            'plateforme'   => $request->header('X-Platform', 'web'),
            'statut'       => 'succes',
            'connected_at' => Carbon::now(),
        ]);

        // Révoquer les anciens tokens agent
        $user->tokens()->where('name', 'agent-token')->delete();

        $token = $user->createToken('agent-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ], 200);
    }

    /**
     * GET /api/agent/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->formatUser($request->user()), 200);
    }

    /**
     * POST /api/agent/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie.'], 200);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'              => $user->id,
            'first_name'      => $user->first_name,
            'last_name'       => $user->last_name,
            'email'           => $user->email,
            'telephone'       => $user->telephone,
            'country'         => $user->country,
            'city'            => $user->city,
            'profile_picture' => $user->profile_picture,
            'role'            => $user->role,
            'status'          => $user->status,
        ];
    }
}
