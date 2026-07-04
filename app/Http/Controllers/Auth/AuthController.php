<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle admin login
     */
    public function login(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        // Récupérer l'utilisateur avec le rôle admin
        $user = User::where('email', $validated['email'])
            ->where('role', 'admin')
            ->first();

        // Vérifier que l'utilisateur existe et le mot de passe est correct
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Identifiants invalides.',
            ]);
        }

        // Vérifier que l'utilisateur est actif
        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => 'Compte désactivé ou bloqué.',
            ]);
        }

        // Créer un token d'authentification (Sanctum)
        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Connexion réussie',
        ], 200);
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        // Révoquer le token actuel
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ], 200);
    }

    /**
     * Get current user
     */
    public function me(Request $request)
    {
        return response()->json($request->user(), 200);
    }
}
