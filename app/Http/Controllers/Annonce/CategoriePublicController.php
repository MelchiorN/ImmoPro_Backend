<?php

namespace App\Http\Controllers\Annonce;

use App\Http\Controllers\Controller;
use App\Models\Categorie;
use Illuminate\Http\JsonResponse;

class CategoriePublicController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/categories
    // Liste des catégories actives (pour afficher les types de biens)
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $categories = Categorie::actif()
            ->orderBy('ordre_affichage')
            ->get()
            ->map(fn ($c) => [
                'slug'        => $c->slug,
                'nom'         => $c->nom,
                'description' => $c->description,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $categories,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/categories/{slug}/schema
    // Retourne les attribut_definitions actifs d'une catégorie.
    // Utilisé par le mobile Flutter et le frontend Nuxt pour générer
    // dynamiquement le formulaire de publication sans code spécifique
    // à chaque type de bien.
    // ─────────────────────────────────────────────────────────────────────────

    public function schema(string $slug): JsonResponse
    {
        $categorie = Categorie::where('slug', $slug)
            ->where('actif', true)
            ->firstOrFail();

        $attributs = $categorie->attributsActifs()
            ->get()
            ->map(fn ($a) => [
                'nom_champ'      => $a->nom_champ,
                'label_affiche'  => $a->label_affiche,
                'type_champ'     => $a->type_champ,
                'options_enum'   => $a->options_enum,   // null sauf pour type enum
                'obligatoire'    => $a->obligatoire,
                'est_socle'      => $a->est_socle,
                'ordre_affichage'=> $a->ordre_affichage,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'slug'        => $categorie->slug,
                'nom'         => $categorie->nom,
                'description' => $categorie->description,
                'attributs'   => $attributs,
            ],
        ]);
    }
}
