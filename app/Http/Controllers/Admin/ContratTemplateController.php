<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContratTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContratTemplateController extends Controller
{
    public const PLACEHOLDERS = [
        '{NOM_LOCATAIRE}'     => 'Nom complet du locataire',
        '{TEL_LOCATAIRE}'     => 'Numéro de téléphone du locataire',
        '{EMAIL_LOCATAIRE}'   => 'Adresse email du locataire',
        '{NOM_PROPRIETAIRE}'  => 'Nom complet du propriétaire ou mandataire',
        '{TEL_PROPRIETAIRE}'  => 'Numéro de téléphone du propriétaire',
        '{TITRE_BIEN}'        => 'Titre du bien immobilier',
        '{ADRESSE_BIEN}'      => 'Adresse / Localisation du bien',
        '{TYPE_BIEN}'         => 'Catégorie du bien (Maison, Villa, Commercial, etc.)',
        '{LOYER_MENSUEL}'     => 'Montant du loyer mensuel (FCFA)',
        '{DATE_DEBUT}'        => 'Date de prise d\'effet de la location',
        '{DUREE_MOIS}'        => 'Durée de la location en mois',
        '{TOTAL_LOCATION}'    => 'Montant total du contrat de location (FCFA)',
    ];

    /**
     * GET /api/admin/contrat-templates
     * Liste tous les modèles de contrats.
     */
    public function index(): JsonResponse
    {
        $templates = ContratTemplate::orderBy('est_defaut', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($templates->isEmpty()) {
            (new \Database\Seeders\ContratTemplateSeeder())->run();
            $templates = ContratTemplate::orderBy('est_defaut', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return response()->json([
            'success'      => true,
            'templates'    => $templates,
            'placeholders' => self::PLACEHOLDERS,
        ]);
    }

    /**
     * GET /api/admin/contrat-templates/{id}
     * Récupère un modèle de contrat spécifique par son ID.
     */
    public function show(string $id = null): JsonResponse
    {
        if (!$id || $id === 'default' || $id === 'active') {
            $template = ContratTemplate::where('est_defaut', true)->first() 
                ?? ContratTemplate::where('est_actif', true)->first();
        } else {
            $template = ContratTemplate::find($id);
        }

        if (!$template) {
            (new \Database\Seeders\ContratTemplateSeeder())->run();
            $template = ContratTemplate::where('est_defaut', true)->first() 
                ?? ContratTemplate::first();
        }

        return response()->json([
            'success'      => true,
            'template'     => $template,
            'placeholders' => self::PLACEHOLDERS,
        ]);
    }

    /**
     * POST /api/admin/contrat-templates
     * Créer un nouveau modèle de contrat.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'titre'        => 'required|string|max:255',
            'description'  => 'nullable|string|max:500',
            'type'         => 'required|string|max:50',
            'contenu_html' => 'required|string',
            'est_actif'    => 'boolean',
            'est_defaut'   => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $estDefaut = $request->boolean('est_defaut', false);
            
            // Si c'est le premier modèle ou s'il est défini par défaut, réinitialiser les autres
            if ($estDefaut || ContratTemplate::count() === 0) {
                ContratTemplate::query()->update(['est_defaut' => false]);
                $estDefaut = true;
            }

            $template = ContratTemplate::create([
                'titre'        => $request->titre,
                'description'  => $request->description,
                'type'         => $request->type ?? 'habitation',
                'contenu_html' => $request->contenu_html,
                'est_actif'    => $request->boolean('est_actif', true),
                'est_defaut'   => $estDefaut,
            ]);

            DB::commit();

            return response()->json([
                'success'  => true,
                'message'  => 'Nouveau modèle de contrat créé avec succès.',
                'template' => $template,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du modèle de contrat.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/admin/contrat-templates/{id}
     * Mettre à jour un modèle de contrat existant.
     */
    public function update(Request $request, string $id = null): JsonResponse
    {
        $request->validate([
            'titre'        => 'required|string|max:255',
            'description'  => 'nullable|string|max:500',
            'type'         => 'nullable|string|max:50',
            'contenu_html' => 'required|string',
            'est_actif'    => 'nullable|boolean',
            'est_defaut'   => 'nullable|boolean',
        ]);

        if (!$id || $id === 'default') {
            $template = ContratTemplate::where('est_defaut', true)->first() 
                ?? ContratTemplate::first();
        } else {
            $template = ContratTemplate::findOrFail($id);
        }

        DB::beginTransaction();
        try {
            if ($request->has('est_defaut') && $request->boolean('est_defaut')) {
                ContratTemplate::where('id', '!=', $template->id)->update(['est_defaut' => false]);
                $template->est_defaut = true;
                $template->est_actif  = true; // Un modèle par défaut est forcément actif
            } elseif ($request->has('est_defaut')) {
                $template->est_defaut = $request->boolean('est_defaut');
            }

            if ($request->has('est_actif')) {
                $template->est_actif = $request->boolean('est_actif');
            }

            $template->titre        = $request->titre;
            if ($request->has('description')) $template->description = $request->description;
            if ($request->has('type'))        $template->type        = $request->type;
            $template->contenu_html = $request->contenu_html;
            $template->save();

            DB::commit();

            return response()->json([
                'success'  => true,
                'message'  => 'Modèle de contrat mis à jour avec succès.',
                'template' => $template,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du modèle.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/admin/contrat-templates/{id}
     * Supprimer un modèle de contrat.
     */
    public function destroy(string $id): JsonResponse
    {
        $template = ContratTemplate::findOrFail($id);

        // Empêcher la suppression si c'est le seul modèle de contrat
        if (ContratTemplate::count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer l\'unique modèle de contrat du système.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $wasDefault = $template->est_defaut;
            $template->delete();

            // Si c'était le modèle par défaut, attribuer le statut "par défaut" au premier modèle restant
            if ($wasDefault) {
                $nextDefault = ContratTemplate::first();
                if ($nextDefault) {
                    $nextDefault->update(['est_defaut' => true, 'est_actif' => true]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Modèle de contrat supprimé avec succès.',
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du modèle.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/admin/contrat-templates/{id}/defaut
     * Définir un modèle comme modèle par défaut.
     */
    public function setDefault(string $id): JsonResponse
    {
        $template = ContratTemplate::findOrFail($id);

        DB::transaction(function () use ($template) {
            ContratTemplate::query()->update(['est_defaut' => false]);
            $template->update([
                'est_defaut' => true,
                'est_actif'  => true,
            ]);
        });

        return response()->json([
            'success'  => true,
            'message'  => "\"{$template->titre}\" est désormais le modèle de contrat par défaut.",
            'template' => $template->fresh(),
        ]);
    }

    /**
     * PATCH /api/admin/contrat-templates/{id}/toggle-status
     * Activer ou désactiver un modèle de contrat.
     */
    public function toggleStatus(string $id): JsonResponse
    {
        $template = ContratTemplate::findOrFail($id);

        // Empêcher de désactiver le modèle par défaut
        if ($template->est_defaut && $template->est_actif) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de désactiver le modèle par défaut. Veuillez choisir un autre modèle par défaut avant de désactiver celui-ci.',
            ], 422);
        }

        $template->est_actif = !$template->est_actif;
        $template->save();

        return response()->json([
            'success'  => true,
            'message'  => $template->est_actif ? 'Modèle activé.' : 'Modèle désactivé.',
            'template' => $template,
        ]);
    }

    /**
     * POST /api/admin/contrat-templates/preview
     * Prévisualiser le rendu d'un contrat avec des données d'exemple dynamiques.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'contenu_html' => 'required|string',
        ]);

        $html = $request->contenu_html;

        $sampleData = [
            '{NOM_LOCATAIRE}'     => 'Kouassi Yves (Exemple)',
            '{TEL_LOCATAIRE}'     => '+228 90 12 34 56',
            '{EMAIL_LOCATAIRE}'   => 'yves.kouassi@example.com',
            '{NOM_PROPRIETAIRE}'  => 'M. Amégankpo Jean (Exemple)',
            '{TEL_PROPRIETAIRE}'  => '+228 91 88 77 66',
            '{TITRE_BIEN}'        => 'Villa Duplex 4 Pièces Standing',
            '{ADRESSE_BIEN}'      => 'Lomé, quartier Bè-Klikamé',
            '{TYPE_BIEN}'         => 'Villa',
            '{LOYER_MENSUEL}'     => '250 000 FCFA',
            '{DATE_DEBUT}'        => date('d/m/Y'),
            '{DUREE_MOIS}'        => '12',
            '{TOTAL_LOCATION}'    => '3 000 000 FCFA',
        ];

        foreach ($sampleData as $key => $value) {
            $html = str_replace($key, $value, $html);
        }

        return response()->json([
            'success'      => true,
            'preview_html' => $html,
        ]);
    }

    /**
     * GET /api/admin/contrat-templates/placeholders
     */
    public function placeholders(): JsonResponse
    {
        return response()->json([
            'success'      => true,
            'placeholders' => self::PLACEHOLDERS,
        ]);
    }
}
