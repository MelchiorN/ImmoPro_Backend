<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    /** Durée de validité de l'OTP (minutes) */
    private const OTP_TTL = 10;

    /**
     * POST /api/register
     *
     * Crée un compte client, génère un OTP et l'envoie par email.
     */
    public function register(Request $request): JsonResponse
    {
        // ── Validation ──────────────────────────────────────────────────────
        $validated = $request->validate([
            'first_name'       => 'required|string|max:100',
            'last_name'        => 'required|string|max:100',
            'email'            => 'required|email|unique:users,email',
            'telephone'        => 'required|string|max:20|unique:users,telephone',
            'country'          => 'required|string|max:100',
            'city'             => 'required|string|max:100',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        // ── Création de l'utilisateur ────────────────────────────────────────
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'telephone'  => $validated['telephone'],
            'country'    => $validated['country'],
            'city'       => $validated['city'],
            'password'   => Hash::make($validated['password']),
            'role'       => 'client',
            'status'     => 'active',
        ]);

        // ── Génération et envoi de l'OTP ─────────────────────────────────────
        $this->generateAndSendOtp($user->email);

        return response()->json([
            'message' => 'Compte créé. Vérifiez votre email pour le code OTP.',
            'email'   => $user->email,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Génère un code OTP, invalide les anciens, envoie par mail.
     */
    public function generateAndSendOtp(string $email): void
    {
        // Invalider les OTP précédents
        Otp::where('email', $email)->update(['utilise' => true]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Otp::create([
            'email'      => $email,
            'code'       => $code,
            'utilise'    => false,
            'expired_at' => Carbon::now()->addMinutes(self::OTP_TTL),
        ]);

        Mail::to($email)->send(new OtpMail($code, self::OTP_TTL));
    }
}
