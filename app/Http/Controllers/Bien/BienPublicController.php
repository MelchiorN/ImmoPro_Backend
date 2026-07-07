<?php

namespace App\Http\Controllers\Bien;

use App\Http\Controllers\Controller;
use App\Http\Resources\BienListResource;
use App\Http\Resources\BienResource;
use App\Models\Bien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BienPublicController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/biens
    // Liste publique des biens publiés, avec filtres et pagination
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type_bien'        => 'nullable|in:appartement,maison,villa,terrain,bureau_commerce',
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

        $query = Bien::with(['medias'])->publie();

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
    // ─────────────────────────────────────────────────────────────────────────

    public function show(string $id): JsonResponse
    {
        $bien = Bien::with(['medias', 'documents', 'proprietaire'])
            ->publie()
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => new BienResource($bien),
        ]);
    }
}
