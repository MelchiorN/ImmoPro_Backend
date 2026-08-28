<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Auth\RegisterController;
use App\Models\HistoriqueConnexion;
use App\Models\Otp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ClientAuthController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/client/login
    // ─────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: "/client/login",
        tags: ["Authentification Client"],
        summary: "Connexion client",
        description: "Authentifie un utilisateur avec le rôle client. Retourne un token Sanctum.",
        operationId: "clientLogin",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "marie.kone@email.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "MonMotDePasse123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Connexion réussie",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Connexion réussie."),
                        new OA\Property(property: "token", type: "string", example: "1|xK3mZ9pQ2rL7nY8sV1jW..."),
                        new OA\Property(property: "user", ref: "#/components/schemas/UserResource")
                    ]
                )
            ),
            new OA\Response(response: 403, description: "Compte suspendu/bloqué ou email non vérifié"),
            new OA\Response(response: 422, description: "Email ou mot de passe incorrect")
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])
                    ->where('role', 'client')
                    ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email ou mot de passe incorrect.',
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est suspendu ou bloqué. Contactez l\'administrateur.',
            ], 403);
        }

        if (is_null($user->email_verified_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez vérifier votre email avec le code OTP avant de vous connecter.',
            ], 403);
        }

        // Enregistrer l'historique de connexion
        HistoriqueConnexion::create([
            'user_id'      => $user->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'device_type'  => 'mobile',
            'plateforme'   => $request->header('X-Platform', 'unknown'),
            'statut'       => 'succes',
            'connected_at' => Carbon::now(),
        ]);

        // Log Spatie
        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['ip' => $request->ip(), 'plateforme' => $request->header('X-Platform', 'mobile')])
            ->log('Connexion client');

        $user->tokens()->where('name', 'client-token')->delete();
        $token = $user->createToken('client-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/verify-otp
    // Étape 2 de l'inscription : vérifier OTP → créer le compte → retourner token
    // Body attendu : { email, otp, pending_token }
    // ─────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: "/verify-otp",
        tags: ["Authentification Client"],
        summary: "Vérification OTP & création du compte (étape 2/2)",
        description: "Vérifie le code OTP reçu par email et crée le compte client.",
        operationId: "verifyOtp",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "otp", "pending_token"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "marie.kone@email.com"),
                    new OA\Property(property: "otp", type: "string", minLength: 6, maxLength: 6, example: "482731"),
                    new OA\Property(property: "pending_token", type: "string", example: "xK3mZ9pQ2rL7nY8sV1jW4tU6bO0cE5fA")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Compte créé avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Compte créé avec succès."),
                        new OA\Property(property: "token", type: "string", example: "2|vR9kP5oN3mQ1eJ8dH..."),
                        new OA\Property(property: "user", ref: "#/components/schemas/UserResource")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Code OTP invalide/expiré")
        ]
    )]
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email'         => 'required|email',
            'otp'           => 'required|string|size:6',
            'pending_token' => 'required|string',
        ]);

        $email        = $request->input('email');
        $code         = $request->input('otp');
        $pendingToken = $request->input('pending_token');
        $cacheKey     = 'pending_registration_' . $pendingToken;

        // ── 1. Vérifier l'OTP ────────────────────────────────────────────────
        $otpRecord = Otp::where('email', $email)
                        ->where('code', $code)
                        ->where('utilise', false)
                        ->where('expired_at', '>', Carbon::now())
                        ->latest()
                        ->first();

        if (! $otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Code OTP invalide ou expiré.',
            ], 422);
        }

        // ── 2. Récupérer les données d'inscription depuis le cache ────────────
        $pendingData = Cache::get($cacheKey);

        if (! $pendingData || $pendingData['email'] !== $email) {
            return response()->json([
                'success' => false,
                'message' => 'Session d\'inscription expirée. Veuillez recommencer l\'inscription.',
            ], 422);
        }

        // ── 3. Tout est valide → créer le compte ──────────────────────────────
        $otpRecord->update(['utilise' => true]);
        Cache::forget($cacheKey);

        $user = User::create([
            'first_name'        => $pendingData['first_name'],
            'last_name'         => $pendingData['last_name'],
            'email'             => $pendingData['email'],
            'telephone'         => $pendingData['telephone'],
            'country'           => $pendingData['country'],
            'city'              => $pendingData['city'],
            'password'          => $pendingData['password'], // déjà hashé dans RegisterController
            'role'              => 'client',
            'status'            => 'active',
            'email_verified_at' => Carbon::now(),
        ]);

        if (is_null($user->email_verified_at)) {
            $user->forceFill(['email_verified_at' => Carbon::now()])->save();
        }

        Log::info('Client email verified after OTP validation.', [
            'user_id' => $user->id,
            'email'   => $user->email,
        ]);

        // Log Spatie
        activity()
            ->performedOn($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('Nouveau compte client créé via OTP');

        // ── 4. Retourner le token ─────────────────────────────────────────────
        $token = $user->createToken('client-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Compte créé avec succès. Bienvenue sur ImmoPro !',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/resend-otp
    // Renvoie un nouveau code OTP (utile si expiré)
    // Body attendu : { email }
    // ─────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: "/resend-otp",
        tags: ["Authentification Client"],
        summary: "Renvoyer un code OTP",
        description: "Génère et renvoie un nouveau code OTP par email.",
        operationId: "resendOtp",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "marie.kone@email.com"),
                    new OA\Property(property: "pending_token", type: "string", nullable: true, example: "xK3mZ9pQ2rL7nY8sV1jW4tU6bO0cE5fA")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Nouveau OTP envoyé",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Un nouveau code OTP a été envoyé.")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Aucune inscription en attente")
        ]
    )]
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email'         => 'required|email',
            'pending_token' => 'nullable|string',
        ]);

        $email        = $request->input('email');
        $pendingToken = $request->input('pending_token');

        // Vérifier qu'il y a bien une inscription en attente dans le cache
        // OU un OTP actif pour cet email (non expiré, non utilisé)
        $hasCache = $pendingToken && Cache::has('pending_registration_' . $pendingToken);

        $hasActiveOtp = Otp::where('email', $email)
                           ->where('utilise', false)
                           ->where('expired_at', '>', Carbon::now())
                           ->exists();

        // Chercher un cache actif via l'email (sans pending_token)
        if (! $hasCache && ! $hasActiveOtp) {
            // Dernière chance : chercher n'importe quel cache pending pour cet email
            // En acceptant le renvoi si un OTP a été généré récemment (même expiré)
            $hadRecentOtp = Otp::where('email', $email)
                              ->where('created_at', '>=', Carbon::now()->subMinutes(60))
                              ->exists();

            if (! $hadRecentOtp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune inscription en attente trouvée pour cet email.',
                ], 404);
            }
        }

        app(RegisterController::class)->generateAndSendOtp($email);

        return response()->json([
            'success' => true,
            'message' => 'Un nouveau code OTP a été envoyé à votre email.',
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/client/me
    // ─────────────────────────────────────────────────────────────────────────
    #[OA\Get(
        path: "/client/me",
        tags: ["Authentification Client"],
        summary: "Profil du client connecté",
        operationId: "clientMe",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Profil récupéré avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "user", ref: "#/components/schemas/UserResource")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user'    => $this->formatUser($request->user()),
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/client/logout
    // ─────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: "/client/logout",
        tags: ["Authentification Client"],
        summary: "Déconnexion client",
        operationId: "clientLogout",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Déconnexion réussie",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Déconnexion réussie.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.',
        ], 200);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────
    private function formatUser(User $user): array
    {
        return [
            'id'                => $user->id,
            'first_name'        => $user->first_name,
            'last_name'         => $user->last_name,
            'email'             => $user->email,
            'telephone'         => $user->telephone,
            'country'           => $user->country,
            'city'              => $user->city,
            'profile_picture'   => $user->profile_picture,
            'role'              => $user->role,
            'status'            => $user->status,
            'email_verified_at' => $user->email_verified_at,
        ];
    }
}
