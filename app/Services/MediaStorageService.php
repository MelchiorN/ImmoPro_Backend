<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service de stockage des médias (photos/vidéos des biens).
 *
 * ─ En local (APP_ENV=local)      : disk 'public' (storage/app/public)
 * ─ En production (APP_ENV!=local): Cloudinary via API HTTP (pas de package)
 *
 * Retourne toujours un tableau :
 *   [
 *     'chemin'  => string,   // chemin relatif (local) ou public_id (cloudinary)
 *     'url'     => string,   // URL publique accessible
 *     'backend' => string,   // 'local' | 'cloudinary'
 *   ]
 */
class MediaStorageService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Upload
    // ─────────────────────────────────────────────────────────────────────────

    public function upload(UploadedFile $fichier, string $dossier): array
    {
        if (app()->isLocal()) {
            return $this->uploadLocal($fichier, $dossier);
        }

        return $this->uploadCloudinary($fichier, $dossier);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Supprimer un fichier
    // ─────────────────────────────────────────────────────────────────────────

    public function delete(string $chemin, string $backend = 'local'): void
    {
        if ($backend === 'cloudinary') {
            $this->deleteCloudinary($chemin);
        } else {
            Storage::disk('public')->delete($chemin);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Obtenir l'URL publique d'un chemin
    // ─────────────────────────────────────────────────────────────────────────

    public function url(string $chemin, string $backend = 'local'): string
    {
        if ($backend === 'cloudinary') {
            // Les URL Cloudinary sont déjà des URLs complètes stockées dans 'chemin'
            return $chemin;
        }

        return Storage::disk('public')->url($chemin);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Déterminer le backend depuis une entrée MediaBien
    // ─────────────────────────────────────────────────────────────────────────

    public static function backendFromChemin(string $chemin): string
    {
        // Les URL Cloudinary commencent par https://res.cloudinary.com
        return str_starts_with($chemin, 'https://res.cloudinary.com') ? 'cloudinary' : 'local';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Stockage local (développement)
    // ─────────────────────────────────────────────────────────────────────────

    private function uploadLocal(UploadedFile $fichier, string $dossier): array
    {
        $chemin = $fichier->store($dossier, 'public');
        $url    = Storage::disk('public')->url($chemin);

        Log::info("[MediaStorage] Upload local : {$chemin}");

        return [
            'chemin'  => $chemin,
            'url'     => $url,
            'backend' => 'local',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cloudinary (production) — API HTTP sans package externe
    // ─────────────────────────────────────────────────────────────────────────

    private function uploadCloudinary(UploadedFile $fichier, string $dossier): array
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey    = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        if (! $cloudName || ! $apiKey || ! $apiSecret) {
            Log::warning('[MediaStorage] Cloudinary non configuré — fallback local.');
            return $this->uploadLocal($fichier, $dossier);
        }

        $timestamp  = time();
        $folder     = 'immopro/' . $dossier;

        // Signature HMAC-SHA1 requise par Cloudinary
        $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
        $signature    = hash_hmac('sha1', $paramsToSign, $apiSecret);

        $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/auto/upload";

        $response = Http::attach(
            'file',
            fopen($fichier->getRealPath(), 'r'),
            $fichier->getClientOriginalName()
        )->post($endpoint, [
            'api_key'   => $apiKey,
            'timestamp' => $timestamp,
            'folder'    => $folder,
            'signature' => $signature,
        ]);

        if (! $response->successful()) {
            Log::error('[MediaStorage] Cloudinary upload échoué : ' . $response->body());
            // Fallback local si Cloudinary échoue
            return $this->uploadLocal($fichier, $dossier);
        }

        $data = $response->json();
        $url  = $data['secure_url'];

        Log::info("[MediaStorage] Upload Cloudinary : {$url}");

        return [
            'chemin'  => $url,         // On stocke l'URL complète comme chemin en production
            'url'     => $url,
            'backend' => 'cloudinary',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Supprimer sur Cloudinary
    // ─────────────────────────────────────────────────────────────────────────

    private function deleteCloudinary(string $publicIdOrUrl): void
    {
        try {
            $cloudName = env('CLOUDINARY_CLOUD_NAME');
            $apiKey    = env('CLOUDINARY_API_KEY');
            $apiSecret = env('CLOUDINARY_API_SECRET');

            if (! $cloudName || ! $apiKey || ! $apiSecret) {
                return;
            }

            // Extraire le public_id depuis l'URL Cloudinary
            // URL format: https://res.cloudinary.com/{cloud}/image/upload/v{v}/{folder}/{id}.{ext}
            $publicId = $publicIdOrUrl;
            if (str_starts_with($publicIdOrUrl, 'http')) {
                preg_match('/upload\/(?:v\d+\/)?(.+)\.[a-z]+$/i', $publicIdOrUrl, $matches);
                $publicId = $matches[1] ?? null;
                if (! $publicId) return;
            }

            $timestamp    = time();
            $paramsToSign = "public_id={$publicId}&timestamp={$timestamp}";
            $signature    = hash_hmac('sha1', $paramsToSign, $apiSecret);

            Http::post("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy", [
                'public_id' => $publicId,
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[MediaStorage] Cloudinary delete échoué : ' . $e->getMessage());
        }
    }
}
