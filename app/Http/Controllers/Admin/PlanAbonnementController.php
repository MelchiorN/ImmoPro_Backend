<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanAbonnement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanAbonnementController extends Controller
{
    /**
     * GET /api/admin/plans-abonnement
     * Liste de tous les plans (actifs + inactifs)
     */
    public function index(): JsonResponse
    {
        $plans = PlanAbonnement::orderBy('ordre')->get();

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    /**
     * POST /api/admin/plans-abonnement
     * Créer un nouveau plan
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'              => 'required|string|max:100|unique:plan_abonnements,nom',
            'description'      => 'nullable|string',
            'nb_publications'  => 'required|integer|min:1',
            'prix'             => 'required|numeric|min:0',
            'ordre'            => 'nullable|integer|min:0',
            'est_actif'        => 'nullable|boolean',
        ]);

        $plan = PlanAbonnement::create([
            'nom'             => $validated['nom'],
            'description'     => $validated['description'] ?? null,
            'nb_publications' => $validated['nb_publications'],
            'prix'            => $validated['prix'],
            'ordre'           => $validated['ordre'] ?? 0,
            'est_actif'       => $validated['est_actif'] ?? true,
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($plan)
            ->log("Plan d'abonnement créé : {$plan->nom}");

        return response()->json([
            'success' => true,
            'message' => 'Plan créé avec succès.',
            'data'    => $plan,
        ], 201);
    }

    /**
     * GET /api/admin/plans-abonnement/{id}
     * Détail d'un plan avec ses souscriptions
     */
    public function show(string $id): JsonResponse
    {
        $plan = PlanAbonnement::withCount('userAbonnements')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $plan,
        ]);
    }

    /**
     * PUT /api/admin/plans-abonnement/{id}
     * Modifier un plan existant
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $plan = PlanAbonnement::findOrFail($id);

        $validated = $request->validate([
            'nom'             => "sometimes|string|max:100|unique:plan_abonnements,nom,{$plan->id}",
            'description'     => 'nullable|string',
            'nb_publications' => 'sometimes|integer|min:1',
            'prix'            => 'sometimes|numeric|min:0',
            'ordre'           => 'nullable|integer|min:0',
            'est_actif'       => 'nullable|boolean',
        ]);

        $plan->update($validated);

        activity()
            ->causedBy($request->user())
            ->performedOn($plan)
            ->log("Plan d'abonnement mis à jour : {$plan->nom}");

        return response()->json([
            'success' => true,
            'message' => 'Plan mis à jour.',
            'data'    => $plan->fresh(),
        ]);
    }

    /**
     * PATCH /api/admin/plans-abonnement/{id}/toggle
     * Activer ou désactiver un plan
     */
    public function toggle(string $id, Request $request): JsonResponse
    {
        $plan = PlanAbonnement::findOrFail($id);
        $plan->update(['est_actif' => !$plan->est_actif]);

        $etat = $plan->est_actif ? 'activé' : 'désactivé';

        activity()
            ->causedBy($request->user())
            ->performedOn($plan)
            ->log("Plan d'abonnement {$etat} : {$plan->nom}");

        return response()->json([
            'success' => true,
            'message' => "Plan {$etat}.",
            'data'    => $plan->fresh(),
        ]);
    }

    /**
     * DELETE /api/admin/plans-abonnement/{id}
     * Soft-delete un plan (ne supprime pas les souscriptions existantes)
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $plan = PlanAbonnement::findOrFail($id);

        // Empêcher la suppression si des abonnements actifs utilisent ce plan
        $abonnementsActifs = $plan->userAbonnements()->where('statut', 'actif')->count();
        if ($abonnementsActifs > 0) {
            return response()->json([
                'success' => false,
                'message' => "Impossible de supprimer ce plan : {$abonnementsActifs} utilisateur(s) l'utilisent activement. Désactivez-le à la place.",
            ], 422);
        }

        $nom = $plan->nom;
        $plan->delete();

        activity()
            ->causedBy($request->user())
            ->withProperties(['plan_id' => $id])
            ->log("Plan d'abonnement supprimé : {$nom}");

        return response()->json([
            'success' => true,
            'message' => 'Plan supprimé.',
        ]);
    }
}
