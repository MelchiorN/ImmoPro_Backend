<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Recu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientPaiementController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/client/paiements
    // Historique des paiements de l'utilisateur connecté (abonnement + frais_etude)
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $perPage = min((int) $request->query('per_page', 20), 50);
        $type    = $request->query('type'); // abonnement | frais_etude

        // Paiements d'abonnement appartenant à l'utilisateur
        $query = Paiement::with(['recu', 'payable'])
            ->where(function ($q) use ($user) {
                // Abonnements
                $q->whereHasMorph('payable', [\App\Models\UserAbonnement::class],
                    fn ($sub) => $sub->where('user_id', $user->id)
                )
                // Frais d'étude
                ->orWhereHasMorph('payable', [\App\Models\Bien::class],
                    fn ($sub) => $sub->where('user_id', $user->id)
                );
            })
            ->latest();

        if ($type) {
            $query->where('type_paiement', $type);
        }

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(fn ($p) => $this->format($p, $user));

        // ── Statistiques globales ──────────────────────────────────────────
        $stats = $this->buildStats($user);

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
            'stats' => $stats,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/client/paiements/{id}/recu
    // Retourne les détails du reçu (JSON ou PDF si dispo)
    // ─────────────────────────────────────────────────────────────────────────

    public function recu(Request $request, string $id): mixed
    {
        $user     = $request->user();
        $paiement = Paiement::with(['recu', 'payable'])
            ->where(function ($q) use ($user) {
                $q->whereHasMorph('payable', [\App\Models\UserAbonnement::class],
                    fn ($sub) => $sub->where('user_id', $user->id)
                )->orWhereHasMorph('payable', [\App\Models\Bien::class],
                    fn ($sub) => $sub->where('user_id', $user->id)
                );
            })
            ->findOrFail($id);

        $recu = $paiement->recu;

        if (! $recu) {
            return response()->json(['success' => false, 'message' => 'Aucun reçu disponible pour ce paiement.'], 404);
        }

        // Si PDF sur disque, le streamer
        if ($recu->fichier_pdf && Storage::disk('local')->exists($recu->fichier_pdf)) {
            return Storage::disk('local')->download(
                $recu->fichier_pdf,
                "recu-{$recu->numero_recu}.pdf",
                ['Content-Type' => 'application/pdf']
            );
        }

        // Sinon JSON complet
        return response()->json([
            'success' => true,
            'data'    => [
                'numero_recu'        => $recu->numero_recu,
                'date_emission'      => $recu->date_emission?->toIso8601String(),
                'montant'            => (float) $paiement->montant,
                'operateur_paiement' => $paiement->operateur_paiement,
                'reference'          => $paiement->reference_transaction,
                'type_paiement'      => $paiement->type_paiement,
                'statut'             => $paiement->statut,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    private function format(Paiement $p, $user): array
    {
        $label = match ($p->type_paiement) {
            'abonnement'  => $this->labelAbo($p),
            'frais_etude' => $this->labelFrais($p),
            default       => 'Paiement',
        };

        return [
            'id'                    => $p->id,
            'type_paiement'         => $p->type_paiement,
            'label'                 => $label,
            'montant'               => (float) $p->montant,
            'operateur_paiement'    => $p->operateur_paiement,
            'statut'                => $p->statut,
            'reference_transaction' => $p->reference_transaction,
            'created_at'            => $p->created_at?->toIso8601String(),
            'recu'                  => $p->recu ? [
                'id'          => $p->recu->id,
                'numero_recu' => $p->recu->numero_recu,
                'date_emission' => $p->recu->date_emission?->toIso8601String(),
            ] : null,
        ];
    }

    private function labelAbo(Paiement $p): string
    {
        $abo = $p->payable;
        if (! $abo) return 'Abonnement';
        $plan = $abo->plan ?? null;
        return 'Abonnement ' . ($plan?->nom ?? '');
    }

    private function labelFrais(Paiement $p): string
    {
        $bien = $p->payable;
        if (! $bien) return "Frais d'étude";
        return "Frais d'étude – " . ($bien->titre ?? 'Bien');
    }

    private function buildStats($user): array
    {
        $paiementsUser = Paiement::where(function ($q) use ($user) {
            $q->whereHasMorph('payable', [\App\Models\UserAbonnement::class],
                fn ($sub) => $sub->where('user_id', $user->id)
            )->orWhereHasMorph('payable', [\App\Models\Bien::class],
                fn ($sub) => $sub->where('user_id', $user->id)
            );
        });

        $totalConfirme     = (clone $paiementsUser)->where('statut', 'confirme')->sum('montant');
        $totalAbonnement   = (clone $paiementsUser)->where('type_paiement', 'abonnement')->where('statut', 'confirme')->sum('montant');
        $totalFraisEtude   = (clone $paiementsUser)->where('type_paiement', 'frais_etude')->where('statut', 'confirme')->sum('montant');
        $nbTransactions    = (clone $paiementsUser)->where('statut', 'confirme')->count();
        $nbEnAttente       = (clone $paiementsUser)->where('statut', 'initie')->count();

        return [
            'total_confirme'    => (float) $totalConfirme,
            'total_abonnements' => (float) $totalAbonnement,
            'total_frais_etude' => (float) $totalFraisEtude,
            'nb_transactions'   => $nbTransactions,
            'nb_en_attente'     => $nbEnAttente,
        ];
    }
}
