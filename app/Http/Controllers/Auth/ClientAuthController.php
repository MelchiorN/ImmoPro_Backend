<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\HistoriqueConnexion;
use App\Models\Otp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ClientAuthController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/client/login
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Authentifie un client par email + mot de passe.
     * Renvoie un token Sanctum si les identifiants sont valides.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])
                    ->where('role', 'client')
                    ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email ou mot de passe incorrect.',
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Votre compte est suspendu ou bloqué.',
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

        // Révoquer les anciens tokens pour éviter l'accumulation
        $user->tokens()->where('name', 'client-token')->delete();

        $token = $user->createToken('client-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/verify-otp
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Valide un OTP reçu par email.
     * Si valide, vérifie l'email de l'utilisateur et retourne un token Sanctum.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp'   => 'required|string|size:6',
        ]);

        /** @var Otp|null $otpRecord */
        $otpRecord = Otp::where('email', $validated['email'])
                        ->where('code', $validated['otp'])
                        ->where('utilise', false)
                        ->where('expired_at', '>', Carbon::now())
                        ->latest()
                        ->first();

        if (!$otpRecord) {
            return response()->json([
                'message' => 'Code OTP invalide ou expiré.',
            ], 422);
        }

        // Marquer le code comme utilisé
        $otpRecord->update(['utilise' => true]);

        // Vérifier l'email de l'utilisateur
        $user = User::where('email', $validated['email'])->firstOrFail();
        if (is_null($user->email_verified_at)) {
            $user->email_verified_at = Carbon::now();
            $user->save();
        }

        // Émettre un token Sanctum
        $token = $user->createToken('client-token')->plainTextToken;

        return response()->json([
            'message' => 'Email vérifié avec succès.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/resend-otp
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Renvoie un nouveau code OTP à l'email de l'utilisateur.
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        /** @var RegisterController $register */
        $register = app(RegisterController::class);
        $register->generateAndSendOtp($validated['email']);

        return response()->json([
            'message' => 'Un nouveau code OTP a été envoyé à votre email.',
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Protégé par auth:sanctum
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/client/me — Retourne le profil de l'utilisateur connecté.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->formatUser($request->user()), 200);
    }

    /**
     * POST /api/client/logout — Révoque le token courant.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Formatte l'objet utilisateur pour la réponse API.
     */
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
