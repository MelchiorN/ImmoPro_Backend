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
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    /**
     * POST /api/login
     *
     * Point d'entrée unique pour admin et agent.
     * Le frontend redirige ensuite selon le champ `role` retourné.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Cherche un admin ou un agent avec cet email
        $user = User::where('email', $validated['email'])
                    ->whereIn('role', ['admin', 'agent'])
                    ->first();

        // Identifiants invalides (utilisateur introuvable ou mauvais mdp)
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        // Compte bloqué ou suspendu
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

        // Log d'activité Spatie
        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['ip' => $request->ip(), 'role' => $user->role])
            ->log('Connexion réussie');

        // Nom du token selon le rôle
        $tokenName = $user->role === 'admin' ? 'admin-token' : 'agent-token';

        // Révoque les anciens tokens du même type pour éviter l'accumulation
        $user->tokens()->where('name', $tokenName)->delete();

        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie.',
            'token'   => $token,
            'user'    => [
                'id'              => $user->id,
                'first_name'      => $user->first_name,
                'last_name'       => $user->last_name,
                'email'           => $user->email,
                'telephone'       => $user->telephone,
                'country'         => $user->country,
                'city'            => $user->city,
                'profile_picture' => $user->profile_picture,
                'role'            => $user->role,   // 'admin' ou 'agent' — le frontend redirige ici
                'status'          => $user->status,
            ],
        ], 200);
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie.'], 200);
    }

    /**
     * GET /api/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user(), 200);
    }

    /**
     * PUT /api/profile
     * Met à jour le profil de l'utilisateur connecté (admin/agent).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'telephone'  => 'nullable|string|max:20',
            'country'    => 'nullable|string|max:100',
            'city'       => 'nullable|string|max:100',
        ]);

        if (($validated['email'] ?? null) !== $user->email) {
            $validated['email_verified_at'] = null;
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour.',
            'user'    => $this->formatUser($user->fresh()),
        ], 200);
    }

    /**
     * POST /api/profile/photo
     * Upload de la photo de profil pour admin/agent.
     */
    public function updateProfilePhoto(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($user->profile_picture) {
            $oldPath = str_replace('/storage/', '', parse_url($user->profile_picture, PHP_URL_PATH));
            Storage::disk('public')->delete($oldPath);
        }

        $file = $request->file('photo');
        $path = $file->store("profiles/{$user->id}", 'public');
        $url  = Storage::disk('public')->url($path);

        $user->update(['profile_picture' => $url]);

        return response()->json([
            'success' => true,
            'message' => 'Photo de profil mise à jour.',
            'profile_picture' => $url,
        ], 200);
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
