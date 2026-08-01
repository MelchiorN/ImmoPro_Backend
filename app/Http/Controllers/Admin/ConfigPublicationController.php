<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfigPublication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigPublicationController extends Controller
{
    /**
     * GET /api/admin/config-publication
     * Retourne la configuration globale actuelle
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ConfigPublication::instance(),
        ]);
    }

    /**
     * PUT /api/admin/config-publication
     * Modifier la configuration globale de publication :
     *  - essais_gratuits_defaut : quota offert à l'inscription
     *  - frais_etude_actifs     : activer/désactiver la collecte des frais d'étude
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'essais_gratuits_defaut'  => 'sometimes|integer|min:0',
            'frais_etude_actifs'      => 'sometimes|boolean',
            // SLA
            'sla1_valeur'             => 'sometimes|integer|min:1',
            'sla1_unite'              => 'sometimes|in:minutes,heures,jours,semaines,mois',
            'sla2_valeur'             => 'sometimes|integer|min:1',
            'sla2_unite'              => 'sometimes|in:minutes,heures,jours,semaines,mois',
            // Visites
            'visite_duree_valeur'     => 'sometimes|integer|min:5',
            'visite_duree_unite'      => 'sometimes|in:minutes,heures,jours,semaines,mois',
            'visite_delai_min_valeur' => 'sometimes|integer|min:0',
            'visite_delai_min_unite'  => 'sometimes|in:minutes,heures,jours,semaines,mois',
        ]);

        if (empty($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun champ valide fourni.',
            ], 422);
        }

        $config = ConfigPublication::instance();
        $config->update($validated);

        activity()
            ->causedBy($request->user())
            ->withProperties($validated)
            ->log('Configuration de publication mise à jour');

        return response()->json([
            'success' => true,
            'message' => 'Configuration mise à jour.',
            'data'    => $config->fresh(),
        ]);
    }

    /**
     * PATCH /api/admin/users/{id}/essais-gratuits
     * Surcharger le quota gratuit d'un utilisateur spécifique
     */
    public function setEssaisGratuits(Request $request, string $id): JsonResponse
    {
        $user = User::where('role', 'client')->findOrFail($id);

        $validated = $request->validate([
            'essais_gratuits_restants' => 'required|integer|min:0',
        ]);

        $ancienneValeur = $user->essais_gratuits_restants;
        $user->update(['essais_gratuits_restants' => $validated['essais_gratuits_restants']]);

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties([
                'ancien'  => $ancienneValeur,
                'nouveau' => $validated['essais_gratuits_restants'],
            ])
            ->log("Quota gratuit modifié pour {$user->first_name} {$user->last_name}");

        return response()->json([
            'success' => true,
            'message' => 'Quota mis à jour.',
            'data'    => [
                'user_id'                  => $user->id,
                'nom'                      => "{$user->first_name} {$user->last_name}",
                'essais_gratuits_restants' => $user->essais_gratuits_restants,
            ],
        ]);
    }
}
