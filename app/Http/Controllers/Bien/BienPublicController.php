<?php

namespace App\Http\Controllers\Bien;

use App\Http\Controllers\Controller;
use App\Http\Resources\BienListResource;
use App\Http\Resources\BienResource;
use App\Models\Bien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BienPublicController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/biens
    // Liste publique des biens publiés, avec filtres et pagination
    // ─────────────────────────────────────────────────────────────────────────

    #[OA\Get(
        path: "/biens",
        tags: ["Biens Immobiliers (Public)"],
        summary: "Lister les biens publiés",
        description: "Retourne la liste paginée des biens immobiliers publiés. Accessible sans authentification.",
        operationId: "listBiensPublics",
        parameters: [
            new OA\Parameter(name: "type_bien", in: "query", description: "Type de bien", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "type_transaction", in: "query", description: "Type de transaction", required: false, schema: new OA\Schema(type: "string", enum: ["vente", "location", "colocation"])),
            new OA\Parameter(name: "prix_min", in: "query", description: "Prix minimum", required: false, schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "prix_max", in: "query", description: "Prix maximum", required: false, schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "surface_min", in: "query", description: "Surface min (m²)", required: false, schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "ville", in: "query", description: "Ville / Adresse", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "search", in: "query", description: "Recherche textuelle", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "sort", in: "query", description: "Tri", required: false, schema: new OA\Schema(type: "string", enum: ["prix_asc", "prix_desc", "date_desc", "surface_desc"])),
            new OA\Parameter(name: "per_page", in: "query", description: "Éléments par page", required: false, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste récupérée avec succès",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "meta", ref: "#/components/schemas/PaginationMeta")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Filtres invalides")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        // Tente l'authentification silencieuse pour enregistrer l'historique de recherche
        // sans bloquer les requêtes anonymes (pas de 401 si token absent).
        \Auth::shouldUse('sanctum');

        $request->validate([
            'type_bien'        => 'nullable|string|max:100',
            'type_transaction' => 'nullable|in:vente,location,colocation',
            'prix_min'         => 'nullable|numeric|min:0',
            'prix_max'         => 'nullable|numeric|min:0',
            'surface_min'      => 'nullable|numeric|min:0',
            'ville'            => 'nullable|string|max:100',
            'search'           => 'nullable|string|max:200',
            'per_page'         => 'nullable|integer|between:1,50',
            'sort'             => 'nullable|in:prix_asc,prix_desc,date_desc,surface_desc',
            // Recherche géographique par rayon
            'lat'              => 'nullable|numeric|between:-90,90',
            'lng'              => 'nullable|numeric|between:-180,180',
            'rayon_km'         => 'nullable|numeric|between:1,100',
        ]);

        $query = Bien::with(['medias', 'categorie'])->publie();

        // ── Filtres ───────────────────────────────────────────────────────────

        if ($type = $request->query('type_bien')) {
            $query->typeBien($type);
        }

        if ($transaction = $request->query('type_transaction')) {
            $query->typeTransaction($transaction);
        }

        $query->prixEntre(
            $request->query('prix_min'),
            $request->query('prix_max')
        );

        if ($surfaceMin = $request->query('surface_min')) {
            $query->where('surface', '>=', $surfaceMin);
        }

        if ($ville = $request->query('ville')) {
            $query->where('adresse', 'like', "%{$ville}%");
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('adresse', 'like', "%{$search}%");
            });
        }

        // ── Recherche géographique (formule Haversine) ────────────────────────

        if ($request->filled(['lat', 'lng', 'rayon_km'])) {
            $lat    = (float) $request->query('lat');
            $lng    = (float) $request->query('lng');
            $rayon  = (float) $request->query('rayon_km');

            $query->selectRaw("
                *,
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?)) * sin(radians(latitude))
                )) AS distance_km
            ", [$lat, $lng, $lat])
            ->having('distance_km', '<=', $rayon)
            ->orderBy('distance_km');
        }

        // ── Tri ───────────────────────────────────────────────────────────────

        match ($request->query('sort', 'date_desc')) {
            'prix_asc'     => $query->orderBy('prix', 'asc'),
            'prix_desc'    => $query->orderBy('prix', 'desc'),
            'surface_desc' => $query->orderBy('surface', 'desc'),
            default        => $query->orderBy('publie_le', 'desc'),
        };

        $biens = $query->paginate($request->query('per_page', 15));

        // ── Enregistrement silencieux de la recherche (utilisateurs auth uniquement) ──
        // Alimente l'historique utilisé par le moteur de recommandation IA.
        if ($request->user()) {
            try {
                \App\Models\HistoriqueRecherche::create([
                    'user_id'          => $request->user()->id,
                    'query_text'       => $request->query('search'),
                    'type_bien'        => $request->query('type_bien'),
                    'type_transaction' => $request->query('type_transaction'),
                    'prix_min'         => $request->query('prix_min'),
                    'prix_max'         => $request->query('prix_max'),
                    'ville'            => $request->query('ville'),
                    'lat'              => $request->query('lat'),
                    'lng'              => $request->query('lng'),
                    'nb_resultats'     => $biens->total(),
                ]);

                // Garder uniquement les 50 dernières recherches pour cet utilisateur
                \App\Models\HistoriqueRecherche::purgerPourUtilisateur($request->user()->id, 50);
            } catch (\Throwable) {
                // Ne jamais bloquer la recherche si l'enregistrement échoue
            }
        }

        return response()->json([
            'success' => true,
            'data'    => BienListResource::collection($biens->items()),
            'meta'    => [
                'total'        => $biens->total(),
                'per_page'     => $biens->perPage(),
                'current_page' => $biens->currentPage(),
                'last_page'    => $biens->lastPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/biens/{id}
    // Détail public d'un bien publié
    // L'injection de Request + tentative d'auth silencieuse permet à BienResource
    // de déverrouiller latitude/longitude pour les clients ayant payé la visite.
    // ─────────────────────────────────────────────────────────────────────────

    #[OA\Get(
        path: "/biens/{id}",
        tags: ["Biens Immobiliers (Public)"],
        summary: "Détail d'un bien publié",
        description: "Retourne le détail complet d'un bien immobilier publié.",
        operationId: "showBienPublic",
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID du bien", schema: new OA\Schema(type: "integer", example: 42))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détail du bien récupéré",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Bien introuvable")
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        // Tente de résoudre l'utilisateur depuis le token Bearer si présent,
        // sans bloquer les requêtes anonymes (pas de 401 si token absent).
        \Auth::shouldUse('sanctum');

        $bien = Bien::with(['medias', 'documents', 'proprietaire', 'agent', 'categorie'])
            ->publie()
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => new BienResource($bien),
        ]);
    }
}
