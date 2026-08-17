<?php

namespace App\Http\Resources;

use App\Services\BienDescriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ressource complète (détail d'un bien).
 * Utilisée pour : GET /biens/{id}, GET /mes-biens/{id}
 *
 * Protection GPS : latitude et longitude sont masquées (null) pour les
 * utilisateurs qui n'ont pas encore payé les frais de visite.
 */
class BienResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user           = $request->user();
        $canSeeAdmin    = $this->canSeeAdminNote($request);
        $hasGps         = $this->resource->canSeeGps($user);
        $hasPaidVisit   = $this->resource->hasPaidVisit($user);
        $canSeeProprio  = $this->resource->canSeeProprioContact($user);

        $descriptionService = app(BienDescriptionService::class);

        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,

            // Classification
            'type_bien'        => $this->type_bien,
            'categorie_nom'    => $this->getCategorie()?->nom ?? ucfirst(str_replace('_', ' ', $this->type_bien)),
            'type_transaction' => $this->type_transaction,

            'titre'                 => $this->titre,
            'description'           => $descriptionService->generer($this->resource),
            'description_originale' => $this->description,
            'desc_personnalisee'    => $this->desc_personnalisee,
            'prix'                  => (float) $this->prix,
            'prix_public'      => $this->prix_public ? (float) $this->prix_public : (float) $this->prix,
            'prix_visite'      => $this->resource->getPrixVisiteEffectif(),
            'visite_payee'     => $hasPaidVisit,   // true si la visite a été payée
            'unite_prix'       => $this->unite_prix,
            'avance_mois'      => $this->avance_mois,
            'caution'          => $this->caution ? (float) $this->caution : null,
            'surface'          => $this->surface ? (float) $this->surface : null,
            'nb_pieces'        => $this->nb_pieces,
            'nb_salles_bain'   => $this->nb_salles_bain,
            'caracteristiques' => $this->caracteristiques ?? [],

            // Localisation — adresse toujours visible
            // latitude/longitude actives si visite payée, admin/agent ou déposant du bien
            'adresse'          => $this->adresse,
            'latitude'         => $hasGps ? (float) $this->latitude : null,
            'longitude'        => $hasGps ? (float) $this->longitude : null,

            // Statut de publication
            'statut'           => $this->statut,
            'publie_le'        => $this->publie_le?->toIso8601String(),

            // Frais d'étude
            'frais_etude_statut' => $this->frais_etude_statut,

            // Note admin (visible pour le déposant du bien, admin, agent)
            'note_admin'       => $this->when($canSeeAdmin, $this->note_admin),

            // Identité du déposant et coordonnées du propriétaire (contact visible uniquement sur visite payée ou admin/agent)
            'role_deposant'            => $this->when($canSeeAdmin, $this->role_deposant),
            'proprietaire_nom'         => $this->when($canSeeProprio, $this->proprietaire_nom ?? $this->proprietaire?->last_name),
            'proprietaire_prenom'      => $this->when($canSeeProprio, $this->proprietaire_prenom ?? $this->proprietaire?->first_name),
            'proprietaire_sexe'        => $this->when($canSeeAdmin, $this->proprietaire_sexe),
            'proprietaire_nationalite' => $this->when($canSeeAdmin, $this->proprietaire_nationalite),
            'proprietaire_telephone'   => $this->when($canSeeProprio, $this->proprietaire_telephone ?? $this->proprietaire?->telephone),
            'proprietaire_email'       => $this->when($canSeeProprio, $this->proprietaire_email ?? $this->proprietaire?->email),
            'proprietaire_adresse'     => $this->when($canSeeAdmin, $this->proprietaire_adresse),

            // Propriétaire du compte
            'proprietaire'     => $this->when($canSeeProprio, fn () => $this->proprietaire ? [
                'id'         => $this->proprietaire->id,
                'first_name' => $this->proprietaire->first_name,
                'last_name'  => $this->proprietaire->last_name,
                'email'      => $this->proprietaire->email,
                'telephone'  => $this->proprietaire->telephone,
            ] : null),

            // Agent assigné
            'agent_id'         => $this->agent_id,
            'agent'            => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id'         => $this->agent->id,
                'first_name' => $this->agent->first_name,
                'last_name'  => $this->agent->last_name,
                'email'      => $this->agent->email,
                'telephone'  => $this->agent->telephone,
            ] : null),

            // Rapport lié
            'rapport'          => $this->whenLoaded('rapport', fn () => $this->rapport ? [
                'id'        => $this->rapport->id,
                'statut'    => $this->rapport->statut,
                'titre'     => $this->rapport->titre,
                'soumis_le' => $this->rapport->soumis_le?->toIso8601String(),
            ] : null),

            // Médias & Documents — toujours visibles
            'medias'           => MediaBienResource::collection($this->whenLoaded('medias')),
            'documents'        => DocumentBienResource::collection($this->whenLoaded('documents')),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    private function canSeeAdminNote(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }
        // Propriétaire du bien, admin et agent voient toujours les coordonnées
        if ($user->id === $this->user_id || in_array($user->role, ['admin', 'agent'])) {
            return true;
        }
        // Un client ayant payé les frais de visite voit aussi les coordonnées du propriétaire
        return $this->resource->hasPaidVisit($user);
    }
}
