<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Paiement;
use App\Models\Recu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Administration des frais d'étude de dossier.
 *
 * GET /api/admin/frais-etude          → liste paginée avec filtres
 * GET /api/admin/frais-etude/stats    → KPIs (total perçu, nb dossiers, etc.)
 */
class AdminFraisEtudeController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/frais-etude/stats
    // ─────────────────────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $total = Paiement::where('type_paiement', 'frais_etude')
            ->where('statut', 'confirme')
            ->sum('montant');

        $nbConfirmes = Paiement::where('type_paiement', 'frais_etude')
            ->where('statut', 'confirme')
            ->count();

        $nbInitie = Paiement::where('type_paiement', 'frais_etude')
            ->where('statut', 'initie')
            ->count();

        $nbEchoue = Paiement::where('type_paiement', 'frais_etude')
            ->whereIn('statut', ['echoue'])
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'montant_total_percu' => (float) $total,
                'nb_confirmes'        => $nbConfirmes,
                'nb_en_attente'       => $nbInitie,
                'nb_echoues'          => $nbEchoue,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/frais-etude
    // Liste paginée avec filtres statut, opérateur, période
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'statut'     => 'nullable|in:initie,confirme,echoue,en_attente',
            'operateur'  => 'nullable|string|max:20',
            'date_debut' => 'nullable|date',
            'date_fin'   => 'nullable|date|after_or_equal:date_debut',
            'per_page'   => 'nullable|integer|between:1,50',
        ]);

        $query = Paiement::with(['payable', 'recu'])
            ->where('type_paiement', 'frais_etude');

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        if ($operateur = $request->query('operateur')) {
            $query->where('operateur_paiement', strtoupper($operateur));
        }

        if ($debut = $request->query('date_debut')) {
            $query->whereDate('created_at', '>=', $debut);
        }

        if ($fin = $request->query('date_fin')) {
            $query->whereDate('created_at', '<=', $fin);
        }

        $paiements = $query->latest()->paginate($request->query('per_page', 20));

        $data = $paiements->getCollection()->map(fn ($p) => $this->formatPaiement($p));

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'total'        => $paiements->total(),
                'per_page'     => $paiements->perPage(),
                'current_page' => $paiements->currentPage(),
                'last_page'    => $paiements->lastPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function formatPaiement(Paiement $p): array
    {
        $bien = $p->payable instanceof Bien ? $p->payable : null;

        return [
            'id'         => $p->id,
            'montant'    => (float) $p->montant,
            'operateur'  => $p->operateur_paiement,
            'statut'     => $p->statut,
            'reference'  => $p->reference_transaction,
            'created_at' => $p->created_at->toIso8601String(),
            'bien'       => $bien ? [
                'id'               => $bien->id,
                'titre'            => $bien->titre,
                'type_bien'        => $bien->type_bien,
                'statut'           => $bien->statut,
                'role_deposant'    => $bien->role_deposant,
                'proprietaire_id'  => $bien->user_id,
            ] : null,
            'recu'       => $p->recu ? [
                'id'          => $p->recu->id,
                'numero_recu' => $p->recu->numero_recu,
                'date'        => $p->recu->date_emission->toIso8601String(),
            ] : null,
        ];
    }
}
