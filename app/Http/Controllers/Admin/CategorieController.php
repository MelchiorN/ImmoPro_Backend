<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttributDefinition;
use App\Models\Categorie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategorieController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/categories
    // Liste toutes les catégories avec le nombre de champs actifs
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $categories = Categorie::withCount([
            'attributs',
            'attributs as attributs_actifs_count' => fn ($q) => $q->where('actif', true),
        ])
        ->orderBy('ordre_affichage')
        ->get()
        ->map(fn ($c) => $this->formatCategorie($c));

        return response()->json([
            'success' => true,
            'data'    => $categories,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/categories/{id}
    // Détail complet avec tous les attribut_definitions
    // ─────────────────────────────────────────────────────────────────────────

    public function show(string $id): JsonResponse
    {
        $categorie = Categorie::with(['attributs'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatCategorieDetail($categorie),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/admin/categories
    // Créer une nouvelle catégorie (avec ses attributs optionnels)
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nom'              => 'required|string|max:100',
            'slug'             => 'required|string|max:50|unique:categories,slug|regex:/^[a-z0-9_]+$/',
            'description'      => 'nullable|string|max:500',
            'ordre_affichage'  => 'nullable|integer|min:0',
            // Attributs initiaux optionnels
            'attributs'        => 'nullable|array',
            'attributs.*.nom_champ'     => 'required|string|max:100|regex:/^[a-z0-9_]+$/',
            'attributs.*.label_affiche' => 'required|string|max:150',
            'attributs.*.type_champ'    => ['required', Rule::in(AttributDefinition::TYPES)],
            'attributs.*.options_enum'  => 'nullable|array',
            'attributs.*.options_enum.*'=> 'string|max:100',
            'attributs.*.obligatoire'   => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $categorie = Categorie::create([
                'nom'             => $request->input('nom'),
                'slug'            => $request->input('slug'),
                'description'     => $request->input('description'),
                'actif'           => true,
                'ordre_affichage' => $request->input('ordre_affichage', 0),
            ]);

            foreach ($request->input('attributs', []) as $index => $attr) {
                AttributDefinition::create([
                    'categorie_id'    => $categorie->id,
                    'nom_champ'       => $attr['nom_champ'],
                    'label_affiche'   => $attr['label_affiche'],
                    'type_champ'      => $attr['type_champ'],
                    'options_enum'    => $attr['options_enum'] ?? null,
                    'obligatoire'     => $attr['obligatoire'] ?? false,
                    'est_socle'       => false, // Les champs créés via l'admin ne sont jamais socle
                    'actif'           => true,
                    'ordre_affichage' => $index + 1,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Catégorie créée avec succès.',
                'data'    => $this->formatCategorieDetail($categorie->fresh(['attributs'])),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/admin/categories/{id}
    // Modifier le nom, description et ordre d'une catégorie
    // (le slug ne peut pas être modifié — il est lié à l'enum type_bien)
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, string $id): JsonResponse
    {
        $categorie = Categorie::findOrFail($id);

        $request->validate([
            'nom'                    => 'sometimes|string|max:100',
            'description'            => 'nullable|string|max:500',
            'actif'                  => 'sometimes|boolean',
            'ordre_affichage'        => 'sometimes|integer|min:0',
            'pourcentage_commission' => 'sometimes|numeric|min:0|max:100',
        ]);

        $categorie->update($request->only([
            'nom', 'description', 'actif', 'ordre_affichage', 'pourcentage_commission',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Catégorie mise à jour.',
            'data'    => $this->formatCategorieDetail($categorie->fresh(['attributs'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/admin/categories/{id}/attributs
    // Ajouter un champ dynamique à une catégorie
    // ─────────────────────────────────────────────────────────────────────────

    public function addAttribut(Request $request, string $id): JsonResponse
    {
        $categorie = Categorie::findOrFail($id);

        $request->validate([
            'nom_champ'     => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                // Unicité dans cette catégorie
                Rule::unique('attribut_definitions')->where('categorie_id', $categorie->id),
            ],
            'label_affiche' => 'required|string|max:150',
            'type_champ'    => ['required', Rule::in(AttributDefinition::TYPES)],
            'options_enum'  => 'nullable|array|required_if:type_champ,enum',
            'options_enum.*'=> 'string|max:100',
            'obligatoire'   => 'nullable|boolean',
        ]);

        // Calculer l'ordre suivant
        $ordreMax = AttributDefinition::where('categorie_id', $categorie->id)->max('ordre_affichage') ?? 0;

        $attribut = AttributDefinition::create([
            'categorie_id'    => $categorie->id,
            'nom_champ'       => $request->input('nom_champ'),
            'label_affiche'   => $request->input('label_affiche'),
            'type_champ'      => $request->input('type_champ'),
            'options_enum'    => $request->input('options_enum'),
            'obligatoire'     => $request->boolean('obligatoire', false),
            'est_socle'       => false,
            'actif'           => true,
            'ordre_affichage' => $ordreMax + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Champ ajouté avec succès.',
            'data'    => $this->formatAttribut($attribut),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/admin/categories/{id}/attributs/{aid}
    // Modifier un champ dynamique (label, options, obligatoire, ordre)
    // Les champs socle ne peuvent pas être rendus non-obligatoires
    // ─────────────────────────────────────────────────────────────────────────

    public function updateAttribut(Request $request, string $id, string $aid): JsonResponse
    {
        $attribut = AttributDefinition::where('categorie_id', $id)->findOrFail($aid);

        $request->validate([
            'label_affiche'  => 'sometimes|string|max:150',
            'options_enum'   => 'nullable|array',
            'options_enum.*' => 'string|max:100',
            'obligatoire'    => 'sometimes|boolean',
            'ordre_affichage'=> 'sometimes|integer|min:1',
        ]);

        // Les champs socle ne peuvent pas devenir non-obligatoires
        if ($attribut->est_socle && $request->has('obligatoire') && ! $request->boolean('obligatoire')) {
            return response()->json([
                'success' => false,
                'message' => 'Un champ socle ne peut pas être rendu facultatif.',
            ], 422);
        }

        $attribut->update($request->only(['label_affiche', 'options_enum', 'obligatoire', 'ordre_affichage']));

        return response()->json([
            'success' => true,
            'message' => 'Champ mis à jour.',
            'data'    => $this->formatAttribut($attribut->fresh()),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/admin/categories/{id}/attributs/{aid}/toggle
    // Activer / Désactiver un champ (alternative à la suppression)
    // Les champs socle ne peuvent pas être désactivés
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleAttribut(string $id, string $aid): JsonResponse
    {
        $attribut = AttributDefinition::where('categorie_id', $id)->findOrFail($aid);

        if ($attribut->est_socle) {
            return response()->json([
                'success' => false,
                'message' => 'Un champ socle ne peut pas être désactivé.',
            ], 422);
        }

        $attribut->update(['actif' => ! $attribut->actif]);

        return response()->json([
            'success' => true,
            'message' => $attribut->actif ? 'Champ activé.' : 'Champ désactivé.',
            'data'    => $this->formatAttribut($attribut),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE /api/admin/categories/{id}/attributs/{aid}
    // Supprimer un champ — bloqué si des biens l'utilisent déjà
    // ─────────────────────────────────────────────────────────────────────────

    public function deleteAttribut(string $id, string $aid): JsonResponse
    {
        $attribut = AttributDefinition::where('categorie_id', $id)->findOrFail($aid);

        // Champ socle : jamais supprimable
        if ($attribut->est_socle) {
            return response()->json([
                'success' => false,
                'message' => 'Un champ socle ne peut pas être supprimé. Désactivez-le si nécessaire.',
            ], 422);
        }

        // Vérifier si au moins un bien stocke une valeur pour ce champ
        $nomChamp  = $attribut->nom_champ;
        $categorie = Categorie::find($id);

        $nbBiensUtilisant = \App\Models\Bien::where('type_bien', $categorie?->slug)
            ->whereNotNull('caracteristiques')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(caracteristiques, '$.{$nomChamp}')) IS NOT NULL")
            ->count();

        if ($nbBiensUtilisant > 0) {
            return response()->json([
                'success' => false,
                'message' => "Ce champ est utilisé par {$nbBiensUtilisant} annonce(s) existante(s). Désactivez-le plutôt que de le supprimer.",
                'nb_biens_concernes' => $nbBiensUtilisant,
            ], 422);
        }

        $attribut->delete();

        return response()->json([
            'success' => true,
            'message' => 'Champ supprimé.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers de formatage
    // ─────────────────────────────────────────────────────────────────────────

    private function formatCategorie(Categorie $c): array
    {
        return [
            'id'                     => $c->id,
            'nom'                    => $c->nom,
            'slug'                   => $c->slug,
            'description'            => $c->description,
            'actif'                  => $c->actif,
            'ordre_affichage'        => $c->ordre_affichage,
            'pourcentage_commission' => (float) $c->pourcentage_commission,
            'nb_attributs'           => $c->attributs_count ?? 0,
            'nb_attributs_actifs'    => $c->attributs_actifs_count ?? 0,
        ];
    }

    private function formatCategorieDetail(Categorie $c): array
    {
        return array_merge($this->formatCategorie($c), [
            'attributs' => $c->attributs->map(fn ($a) => $this->formatAttribut($a))->values(),
        ]);
    }

    private function formatAttribut(AttributDefinition $a): array
    {
        return [
            'id'               => $a->id,
            'nom_champ'        => $a->nom_champ,
            'label_affiche'    => $a->label_affiche,
            'type_champ'       => $a->type_champ,
            'options_enum'     => $a->options_enum,
            'obligatoire'      => $a->obligatoire,
            'est_socle'        => $a->est_socle,
            'actif'            => $a->actif,
            'ordre_affichage'  => $a->ordre_affichage,
        ];
    }
}
