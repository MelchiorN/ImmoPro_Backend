<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ressource complète (détail d'un bien).
 * Utilisée pour : GET /biens/{id}, GET /mes-biens/{id}
 */
class BienResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,

            // Classification
            'type_bien'        => $this->type_bien,
            'type_transaction' => $this->type_transaction,

            // Infos
            'titre'            => $this->titre,
            'description'      => $this->description,
            'prix'             => (float) $this->prix,
            'surface'          => $this->surface ? (float) $this->surface : null,
            'nb_pieces'        => $this->nb_pieces,
            'nb_salles_bain'   => $this->nb_salles_bain,
            'caracteristiques' => $this->caracteristiques ?? [],

            // Localisation
            'adresse'          => $this->adresse,
            'latitude'         => (float) $this->latitude,
            'longitude'        => (float) $this->longitude,

            // Statut
            'statut'           => $this->statut,
            'publie_le'        => $this->publie_le?->toIso8601String(),

            // Note admin (visible uniquement pour le propriétaire ou admin)
            'note_admin'       => $this->when(
                $this->canSeeAdminNote($request),
                $this->note_admin
            ),

            // Propriétaire (allégé)
            'proprietaire'     => $this->whenLoaded('proprietaire', fn () => [
                'id'         => $this->proprietaire->id,
                'first_name' => $this->proprietaire->first_name,
                'last_name'  => $this->proprietaire->last_name,
                'email'      => $this->proprietaire->email,
                'telephone'  => $this->proprietaire->telephone,
            ]),

            // Agent assigné
            'agent_id'         => $this->agent_id,
            'agent'            => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id'         => $this->agent->id,
                'first_name' => $this->agent->first_name,
                'last_name'  => $this->agent->last_name,
                'email'      => $this->agent->email,
            ] : null),

            // Rapport lié
            'rapport'          => $this->whenLoaded('rapport', fn () => $this->rapport ? [
                'id'        => $this->rapport->id,
                'statut'    => $this->rapport->statut,
                'titre'     => $this->rapport->titre,
                'soumis_le' => $this->rapport->soumis_le?->toIso8601String(),
            ] : null),

            // Relations
            'medias'           => MediaBienResource::collection(
                $this->whenLoaded('medias')
            ),
            'documents'        => DocumentBienResource::collection(
                $this->whenLoaded('documents')
            ),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    private function canSeeAdminNote(Request $request): bool
    {
        $user = $request->user();
        if (! $user) return false;

        return $user->id === $this->user_id
            || in_array($user->role, ['admin', 'agent']);
    }
}
