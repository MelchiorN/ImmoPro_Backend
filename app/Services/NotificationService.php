<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Service centralisé de notifications.
 *
 * Chaque appel à notify() :
 *  1. Enregistre la notification en base (in-app)
 *  2. Envoie un push FCM HTTP v1 (Service Account JSON) si device_token présent
 *  3. Envoie un email HTML si $emailBody est fourni
 */
class NotificationService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Méthode principale
    // ─────────────────────────────────────────────────────────────────────────

    public function notify(
        User    $user,
        string  $type,
        string  $titre,
        string  $message,
        array   $data          = [],
        ?string $emailSubject  = null,
        ?string $emailBody     = null,
    ): void {
        // 1. In-app (base de données)
        $this->saveInApp($user, $type, $titre, $message, $data);

        // 2. Push FCM v1 (si token enregistré)
        if ($user->device_token) {
            $this->sendFcmV1Push($user->device_token, $titre, $message, $data);
        }

        // 3. Email
        if ($emailSubject && $emailBody && $user->email) {
            $this->sendEmail($user->email, $emailSubject, $emailBody);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Notification in-app
    // ─────────────────────────────────────────────────────────────────────────

    private function saveInApp(User $user, string $type, string $titre, string $message, array $data): void
    {
        try {
            Notification::create([
                'user_id' => $user->id,
                'type'    => $type,
                'titre'   => $titre,
                'message' => $message,
                'canal'   => 'push',
                'lu'      => false,
                'data'    => $data,
            ]);
        } catch (\Throwable $e) {
            Log::warning("[NotificationService] Échec in-app : {$e->getMessage()}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. FCM HTTP v1 — Service Account OAuth2
    // ─────────────────────────────────────────────────────────────────────────

    private function sendFcmV1Push(string $deviceToken, string $title, string $body, array $data = []): void
    {
        try {
            $accessToken = $this->getFcmAccessToken();
            if (! $accessToken) {
                Log::info('[NotificationService] Pas de token FCM OAuth2 — push ignoré.');
                return;
            }

            $serviceAccount = $this->loadServiceAccount();
            $projectId      = $serviceAccount['project_id'];

            $payload = [
                'message' => [
                    'token'        => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    // Données supplémentaires (toutes en string pour FCM)
                    'data'         => array_map('strval', $data),
                    'android'      => [
                        'priority' => 'high',
                        'notification' => [
                            'sound'        => 'default',
                            'channel_id'   => 'immopro_notifs',
                        ],
                    ],
                    'apns'         => [
                        'payload' => [
                            'aps' => [
                                'sound'             => 'default',
                                'content-available' => 1,
                            ],
                        ],
                    ],
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type'  => 'application/json',
            ])->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                $payload
            );

            if (! $response->successful()) {
                Log::warning('[NotificationService] FCM v1 push failed: ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning("[NotificationService] Exception FCM v1 : {$e->getMessage()}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Obtenir un access token OAuth2 (mis en cache 50 min)
    // ─────────────────────────────────────────────────────────────────────────

    private function getFcmAccessToken(): ?string
    {
        // Les tokens OAuth2 Google durent 60 min — on cache 50 min pour la marge
        return Cache::remember('fcm_oauth_access_token', 50 * 60, function () {
            return $this->generateOAuthToken();
        });
    }

    private function generateOAuthToken(): ?string
    {
        try {
            $sa  = $this->loadServiceAccount();
            $now = time();

            // ── Construire le JWT (RS256) ─────────────────────────────────────
            $header = $this->base64UrlEncode(json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ]));

            $claims = $this->base64UrlEncode(json_encode([
                'iss'   => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));

            $signingInput = "{$header}.{$claims}";

            // Signer avec la clé privée RSA
            $privateKey = openssl_pkey_get_private($sa['private_key']);
            if (! $privateKey) {
                Log::error('[NotificationService] Impossible de charger la clé privée Firebase.');
                return null;
            }

            $signature = '';
            if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                Log::error('[NotificationService] Échec signature JWT Firebase.');
                return null;
            }

            $jwt = "{$signingInput}." . $this->base64UrlEncode($signature);

            // ── Échanger le JWT contre un access token ────────────────────────
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if (! $response->successful()) {
                Log::error('[NotificationService] OAuth2 token exchange failed: ' . $response->body());
                return null;
            }

            return $response->json('access_token');

        } catch (\Throwable $e) {
            Log::error("[NotificationService] generateOAuthToken exception : {$e->getMessage()}");
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Charger le fichier Service Account JSON
    // ─────────────────────────────────────────────────────────────────────────

    private function loadServiceAccount(): array
    {
        $path = storage_path('app/firebase/immopro.json');

        if (! file_exists($path)) {
            throw new \RuntimeException("Fichier Service Account Firebase introuvable : {$path}");
        }

        $content = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($content['private_key'])) {
            throw new \RuntimeException('Fichier Service Account Firebase invalide.');
        }

        return $content;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Email HTML
    // ─────────────────────────────────────────────────────────────────────────

    private function sendEmail(string $emailAddress, string $subject, string $htmlBody): void
    {
        try {
            Mail::html($htmlBody, function ($message) use ($emailAddress, $subject) {
                $message->to($emailAddress)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning("[NotificationService] Email non envoyé à {$emailAddress} : {$e->getMessage()}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper : Base64 URL-safe encoding (sans padding)
    // ─────────────────────────────────────────────────────────────────────────

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
