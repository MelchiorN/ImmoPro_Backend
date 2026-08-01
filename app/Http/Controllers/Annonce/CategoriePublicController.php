<?php

namespace App\Http\Controllers\Annonce;

use App\Http\Controllers\Controller;
use App\Models\Categorie;
use App\Models\TypeLogement;
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
                'options_enum'   => $a->options_enum,
                'obligatoire'    => $a->obligatoire,
                'est_socle'      => $a->est_socle,
                'ordre_affichage'=> $a->ordre_affichage,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'slug'                    => $categorie->slug,
                'nom'                     => $categorie->nom,
                'description'             => $categorie->description,
                'pourcentage_commission'  => (float) $categorie->pourcentage_commission,
                'frais_etude_pourcentage' => (float) ($categorie->frais_etude_pourcentage ?? 0),
                'attributs'               => $attributs,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/categories/{slug}/types-logement
    // Retourne les types de logement actifs et ordonnés pour une catégorie.
    // Principalement utilisé pour "appartement" (Studio/F1, F2, F3, F4+…).
    // Configurable depuis l'administration sans toucher au code.
    // ─────────────────────────────────────────────────────────────────────────

    public function typesLogement(string $slug): JsonResponse
    {
        $categorie = Categorie::where('slug', $slug)
            ->where('actif', true)
            ->firstOrFail();

        $types = $categorie->typesLogement()
            ->get()
            ->map(fn ($t) => [
                'slug'        => $t->slug,
                'nom'         => $t->nom,
                'description' => $t->description,
                'est_socle'   => $t->est_socle,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $types,
        ]);
    }
}
