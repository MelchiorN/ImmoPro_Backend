<?php

namespace App\Http\Resources;

use App\Services\BienDescriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ressource allégée pour les listes (paginations).
 * Utilisée pour : GET /biens, GET /mes-biens
 *
 * Protection GPS : latitude et longitude sont masquées (null) pour les
 * utilisateurs qui n'ont pas encore payé les frais de visite.
 */
class BienListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user         = $request->user();
        $hasGps       = $this->resource->canSeeGps($user);
        $hasPaidVisit = $this->resource->hasPaidVisit($user);

        // Photo principale uniquement
        $photo = $this->medias
            ->where('est_principale', true)
            ->first()
            ?? $this->medias->first();

        $descriptionService = app(BienDescriptionService::class);

        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'type_bien'        => $this->type_bien,
            'categorie_nom'    => $this->getCategorie()?->nom ?? ucfirst(str_replace('_', ' ', $this->type_bien)),
            'type_transaction' => $this->type_transaction,
            'titre'            => $this->titre,
            'description'      => $descriptionService->generer($this->resource),
            'prix'             => (float) $this->prix,
            'prix_public'      => $this->prix_public ? (float) $this->prix_public : (float) $this->prix,
            'prix_visite'      => $this->resource->getPrixVisiteEffectif(),
            'visite_payee'     => $hasPaidVisit,
            'unite_prix'       => $this->unite_prix,
            'avance_mois'      => $this->avance_mois,
            'caution'          => $this->caution ? (float) $this->caution : null,
            'surface'          => $this->surface ? (float) $this->surface : null,
            'nb_pieces'        => $this->nb_pieces,
            'caracteristiques' => $this->caracteristiques ?? [],

            // Adresse visible, coordonnées protégées
            'adresse'          => $this->adresse,
            'latitude'         => $hasGps ? (float) $this->latitude : null,
            'longitude'        => $hasGps ? (float) $this->longitude : null,

            'statut'           => $this->statut,
            'frais_etude_statut' => $this->frais_etude_statut,
            'role_deposant'    => $this->role_deposant,
            'photo_principale' => $photo
                ? $photo->url
                : null,
            'medias'           => $this->medias->map(fn ($m) => [
                'id'             => $m->id,
                'type'           => $m->type === 'photo' ? 'image' : $m->type,
                'url'            => $m->url,
                'est_principale' => (bool) $m->est_principale,
                'ordre'          => $m->ordre,
            ])->values()->toArray(),
            'publie_le'        => $this->publie_le?->toIso8601String(),
            'created_at'       => $this->created_at->toIso8601String(),
            'proprietaire'     => $this->proprietaire ? [
                'first_name' => $this->proprietaire->first_name,
                'last_name'  => $this->proprietaire->last_name,
                'email'      => $this->proprietaire->email,
            ] : null,
            'rapport'          => $this->whenLoaded('rapport', function () {
                $r = $this->rapport;
                return $r ? [
                    'id'        => $r->id,
                    'statut'    => $r->statut,
                    'soumis_le' => $r->soumis_le?->toIso8601String(),
                    'titre'     => $r->titre,
                ] : null;
            }),
            'agent'            => $this->whenLoaded('agent', function () {
                $a = $this->agent;
                return $a ? [
                    'id'         => $a->id,
                    'first_name' => $a->first_name,
                    'last_name'  => $a->last_name,
                    'email'      => $a->email,
                ] : null;
            }),
        ];
    }
}
