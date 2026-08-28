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
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/login",
        tags: ["Authentification Admin / Agent"],
        summary: "Connexion admin ou agent",
        description: "Authentifie un utilisateur avec le rôle admin ou agent.",
        operationId: "adminAgentLogin",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "admin@immopro.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "MotDePasse123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Connexion réussie",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Connexion réussie."),
                        new OA\Property(property: "token", type: "string", example: "3|aB7cD9eF2gH4iJ6kL..."),
                        new OA\Property(property: "user", ref: "#/components/schemas/UserResource")
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Compte bloqué ou suspendu"),
            new OA\Response(response: 422, description: "Identifiants incorrects")
        ]
    )]
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
                'email' => ['Identifiants incorrects.'],
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

    #[OA\Post(
        path: "/logout",
        tags: ["Authentification Admin / Agent"],
        summary: "Déconnexion admin ou agent",
        operationId: "adminAgentLogout",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Déconnexion réussie",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Déconnexion réussie.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Log d'activité Spatie
        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->log('Déconnexion');

        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie.'], 200);
    }

    #[OA\Get(
        path: "/me",
        tags: ["Authentification Admin / Agent"],
        summary: "Profil de l'utilisateur connecté (admin/agent)",
        operationId: "adminAgentMe",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Profil récupéré",
                content: new OA\JsonContent(ref: "#/components/schemas/UserResource")
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user(), 200);
    }

    #[OA\Put(
        path: "/profile",
        tags: ["Authentification Admin / Agent"],
        summary: "Mise à jour du profil (admin/agent)",
        operationId: "updateProfile",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["first_name", "last_name", "email"],
                properties: [
                    new OA\Property(property: "first_name", type: "string", maxLength: 100, example: "Ahmed"),
                    new OA\Property(property: "last_name", type: "string", maxLength: 100, example: "Diallo"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "ahmed.diallo@immopro.com"),
                    new OA\Property(property: "telephone", type: "string", nullable: true, example: "+22501000000"),
                    new OA\Property(property: "country", type: "string", nullable: true, example: "Côte d'Ivoire"),
                    new OA\Property(property: "city", type: "string", nullable: true, example: "Abidjan")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Profil mis à jour avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Profil mis à jour."),
                        new OA\Property(property: "user", ref: "#/components/schemas/UserResource")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )]
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

    #[OA\Post(
        path: "/profile/photo",
        tags: ["Authentification Admin / Agent"],
        summary: "Changer la photo de profil (admin/agent)",
        operationId: "updateProfilePhoto",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["photo"],
                    properties: [
                        new OA\Property(property: "photo", type: "string", format: "binary", description: "Image JPG, PNG ou WebP (max 5 Mo)")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Photo mise à jour",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Photo de profil mise à jour."),
                        new OA\Property(property: "profile_picture", type: "string", format: "url", example: "https://api.immopro.com/storage/profiles/1/photo.jpg")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Fichier invalide")
        ]
    )]
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
