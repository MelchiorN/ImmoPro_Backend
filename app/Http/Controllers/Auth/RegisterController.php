<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

class RegisterController extends Controller
{
    /** Durée de validité de l'OTP (minutes) */
    private const OTP_TTL = 10;

    /** Durée de vie des données pending en cache (minutes) — plus long que l'OTP */
    private const PENDING_TTL = 30;

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/register
    // Étape 1 : validation + stockage temporaire + envoi OTP
    // ─────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: "/register",
        tags: ["Authentification Client"],
        summary: "Inscription client (étape 1/2)",
        description: "Valide les données du formulaire, les stocke temporairement en cache et envoie un code OTP à l'email fourni.",
        operationId: "clientRegister",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["first_name", "last_name", "email", "telephone", "country", "city", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "first_name", type: "string", maxLength: 100, example: "Marie"),
                    new OA\Property(property: "last_name", type: "string", maxLength: 100, example: "Koné"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "marie.kone@email.com"),
                    new OA\Property(property: "telephone", type: "string", maxLength: 20, example: "+22507654321"),
                    new OA\Property(property: "country", type: "string", maxLength: 100, example: "Côte d'Ivoire"),
                    new OA\Property(property: "city", type: "string", maxLength: 100, example: "Abidjan"),
                    new OA\Property(property: "password", type: "string", format: "password", minLength: 8, example: "MonMotDePasse123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "MonMotDePasse123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "OTP envoyé avec succès — en attente de validation",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Code OTP envoyé à votre email."),
                        new OA\Property(property: "email", type: "string", format: "email", example: "marie.kone@email.com"),
                        new OA\Property(property: "pending_token", type: "string", example: "xK3mZ9pQ2rL7nY8sV1jW4tU6bO0cE5fA")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Erreur de validation", ref: "#/components/schemas/ErrorValidation")
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        // ── Validation ────────────────────────────────────────────────────────
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'telephone'  => 'required|string|max:20|unique:users,telephone',
            'country'    => 'required|string|max:100',
            'city'       => 'required|string|max:100',
            'password'   => ['required', 'confirmed', Password::min(8)],
        ]);

        // ── Stocker les données en cache (fonctionne API stateless) ──────────
        $pendingToken = Str::random(40);
        $cacheKey     = 'pending_registration_' . $pendingToken;

        Cache::put($cacheKey, [
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'telephone'  => $validated['telephone'],
            'country'    => $validated['country'],
            'city'       => $validated['city'],
            'password'   => Hash::make($validated['password']),
        ], now()->addMinutes(self::PENDING_TTL));

        // ── Générer et envoyer l'OTP ──────────────────────────────────────────
        $this->generateAndSendOtp($validated['email']);

        return response()->json([
            'success'       => true,
            'message'       => 'Code OTP envoyé à votre email. Vous avez 10 minutes pour le valider.',
            'email'         => $validated['email'],
            'pending_token' => $pendingToken,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Méthode utilitaire — génère et envoie l'OTP
    // Utilisée aussi par ClientAuthController::resendOtp()
    // ─────────────────────────────────────────────────────────────────────────
    public function generateAndSendOtp(string $email): string
    {
        // Invalider les OTP précédents pour cet email
        Otp::where('email', $email)->update(['utilise' => true]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Otp::create([
            'email'      => $email,
            'code'       => $code,
            'utilise'    => false,
            'expired_at' => Carbon::now()->addMinutes(self::OTP_TTL),
        ]);

        try {
            Mail::to($email)->send(new OtpMail($code, self::OTP_TTL));
            Log::info('OTP email sent successfully.', [
                'email' => $email,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OTP email could not be sent.', [
                'email'     => $email,
                'exception' => $e->getMessage(),
                'mailer'    => config('mail.default'),
                'host'      => config('mail.mailers.smtp.host') ?? null,
                'port'      => config('mail.mailers.smtp.port') ?? null,
                'encryption'=> config('mail.mailers.smtp.encryption') ?? null,
            ]);
        }

        return $code;
    }
}
