<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Location;
use App\Models\Reversement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/commissions/stats
    // KPIs financiers : total perçu, par période, reversements en attente
    // ─────────────────────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $totalPercu = Commission::sum('montant_gagne');

        $percuCeMois = Commission::whereMonth('date_prelevement', now()->month)
            ->whereYear('date_prelevement', now()->year)
            ->sum('montant_gagne');

        $percuMoisDernier = Commission::whereMonth('date_prelevement', now()->subMonth()->month)
            ->whereYear('date_prelevement', now()->subMonth()->year)
            ->sum('montant_gagne');

        $nbLocationsActives = Location::where('statut', 'actif')->count();

        $reversementsEnAttente = Reversement::where('statut', 'en_attente')
            ->sum('montant_a_reverser');

        $nbReversementsEnAttente = Reversement::where('statut', 'en_attente')->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_percu'               => round((float) $totalPercu, 2),
                'percu_ce_mois'             => round((float) $percuCeMois, 2),
                'percu_mois_dernier'        => round((float) $percuMoisDernier, 2),
                'nb_locations_actives'      => $nbLocationsActives,
                'reversements_en_attente'   => round((float) $reversementsEnAttente, 2),
                'nb_reversements_en_attente'=> $nbReversementsEnAttente,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/commissions
    // Liste des commissions paginées
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $commissions = Commission::with([
            'location.bien',
            'location.locataire',
            'location.proprietaire',
        ])
        ->orderByDesc('date_prelevement')
        ->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $commissions->map(fn($c) => $this->formatCommission($c)),
            'meta'    => [
                'total'        => $commissions->total(),
                'per_page'     => $commissions->perPage(),
                'current_page' => $commissions->currentPage(),
                'last_page'    => $commissions->lastPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/reversements
    // Liste des reversements paginés
    // ─────────────────────────────────────────────────────────────────────────

    public function reversements(Request $request): JsonResponse
    {
        $query = Reversement::with([
            'proprietaire',
            'location.bien',
        ])->orderByDesc('created_at');

        if ($request->query('statut')) {
            $query->where('statut', $request->query('statut'));
        }

        $reversements = $query->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $reversements->map(fn($r) => $this->formatReversement($r)),
            'meta'    => [
                'total'        => $reversements->total(),
                'per_page'     => $reversements->perPage(),
                'current_page' => $reversements->currentPage(),
                'last_page'    => $reversements->lastPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/admin/reversements/{id}/traiter
    // Marquer un reversement comme traité (virement effectué)
    // ─────────────────────────────────────────────────────────────────────────

    public function traiterReversement(string $id): JsonResponse
    {
        $reversement = Reversement::findOrFail($id);

        if ($reversement->statut === 'traite') {
            return response()->json([
                'success' => false,
                'message' => 'Ce reversement a déjà été traité.',
            ], 422);
        }

        $reversement->update([
            'statut'       => 'traite',
            'date_paiement'=> now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reversement marqué comme traité.',
            'data'    => $this->formatReversement($reversement->fresh(['proprietaire', 'location.bien'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function formatCommission(Commission $c): array
    {
        $bien = $c->location?->bien;
        return [
            'id'                   => $c->id,
            'montant_gagne'        => (float) $c->montant_gagne,
            'pourcentage_applique' => (float) $c->pourcentage_applique,
            'date_prelevement'     => $c->date_prelevement?->toIso8601String(),
            'bien'                 => $bien ? ['titre' => $bien->titre, 'adresse' => $bien->adresse] : null,
            'locataire'            => $c->location?->locataire
                ? $c->location->locataire->first_name . ' ' . $c->location->locataire->last_name
                : null,
            'duree_mois'           => $c->location?->duree_mois,
        ];
    }

    private function formatReversement(Reversement $r): array
    {
        $bien = $r->location?->bien;
        return [
            'id'                => $r->id,
            'montant_a_reverser'=> (float) $r->montant_a_reverser,
            'statut'            => $r->statut,
            'date_paiement'     => $r->date_paiement?->toIso8601String(),
            'created_at'        => $r->created_at?->toIso8601String(),
            'proprietaire'      => $r->proprietaire
                ? ['nom' => $r->proprietaire->first_name . ' ' . $r->proprietaire->last_name, 'email' => $r->proprietaire->email]
                : null,
            'bien'              => $bien ? ['titre' => $bien->titre, 'adresse' => $bien->adresse] : null,
        ];
    }
}
