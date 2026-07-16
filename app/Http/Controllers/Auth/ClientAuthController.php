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
use Illuminate\Validation\ValidationException;

class ClientAuthController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/client/login
    // ─────────────────────────────────────────────────────────────────────────
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
