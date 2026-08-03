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
        $user   = $request->user();
        $canSee = $this->canSeeAdminNote($request);
        $hasGps = $this->resource->hasPaidVisit($user);

        $descriptionService = app(BienDescriptionService::class);

        return [
            'id'               => $this->id,

            // Classification
            'type_bien'        => $this->type_bien,
            'categorie_nom'    => $this->getCategorie()?->nom ?? ucfirst(str_replace('_', ' ', $this->type_bien)),
            'type_transaction' => $this->type_transaction,

            // Infos de base
            'titre'            => $this->titre,
            'description'      => $descriptionService->generer($this->resource),
            'prix'             => (float) $this->prix,
            'prix_public'      => $this->prix_public ? (float) $this->prix_public : (float) $this->prix,
            'prix_visite'      => $this->resource->getPrixVisiteEffectif(),
            'visite_payee'     => $hasGps,   // indique au mobile s'il peut afficher la carte
            'unite_prix'       => $this->unite_prix,
            'avance_mois'      => $this->avance_mois,
            'caution'          => $this->caution ? (float) $this->caution : null,
            'surface'          => $this->surface ? (float) $this->surface : null,
            'nb_pieces'        => $this->nb_pieces,
            'nb_salles_bain'   => $this->nb_salles_bain,
            'caracteristiques' => $this->caracteristiques ?? [],

            // Localisation — adresse toujours visible
            // latitude/longitude protégées : null tant que la visite n'est pas payée
            'adresse'          => $this->adresse,
            'latitude'         => $hasGps ? (float) $this->latitude : null,
            'longitude'        => $hasGps ? (float) $this->longitude : null,

            // Statut de publication
            'statut'           => $this->statut,
            'publie_le'        => $this->publie_le?->toIso8601String(),

            // Frais d'étude
            'frais_etude_statut' => $this->frais_etude_statut,

            // Note admin (visible pour le propriétaire, admin, agent)
            'note_admin'       => $this->when($canSee, $this->note_admin),

            // Identité du déposant (visible pour proprio, admin, agent)
            'role_deposant'            => $this->when($canSee, $this->role_deposant),
            'proprietaire_nom'         => $this->when($canSee, $this->proprietaire_nom),
            'proprietaire_prenom'      => $this->when($canSee, $this->proprietaire_prenom),
            'proprietaire_sexe'        => $this->when($canSee, $this->proprietaire_sexe),
            'proprietaire_nationalite' => $this->when($canSee, $this->proprietaire_nationalite),
            'proprietaire_telephone'   => $this->when($canSee, $this->proprietaire_telephone),
            'proprietaire_email'       => $this->when($canSee, $this->proprietaire_email),
            'proprietaire_adresse'     => $this->when($canSee, $this->proprietaire_adresse),

            // Propriétaire du compte
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
