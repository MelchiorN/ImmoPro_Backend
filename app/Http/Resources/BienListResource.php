<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ressource allégée pour les listes (paginations).
 * Utilisée pour : GET /biens, GET /mes-biens
 */
class BienListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Photo principale uniquement
        $photo = $this->medias
            ->where('est_principale', true)
            ->first()
            ?? $this->medias->first();

        return [
            'id'               => $this->id,
            'type_bien'        => $this->type_bien,
            'type_transaction' => $this->type_transaction,
            'titre'            => $this->titre,
            'prix'             => (float) $this->prix,
            'surface'          => $this->surface ? (float) $this->surface : null,
            'nb_pieces'        => $this->nb_pieces,
            'adresse'          => $this->adresse,
            'latitude'         => (float) $this->latitude,
            'longitude'        => (float) $this->longitude,
            'statut'           => $this->statut,
            'photo_principale' => $photo
                ? $photo->url_publique
                : null,
            'publie_le'        => $this->publie_le?->toIso8601String(),
            'created_at'       => $this->created_at->toIso8601String(),
        ];
    }
}
