<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Recu;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientPaiementController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/client/paiements
    // Historique des paiements de l'utilisateur connecté (abonnement + frais_etude + visite)
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $perPage = min((int) $request->query('per_page', 20), 50);
        $type    = $request->query('type'); // abonnement | frais_etude | visite

        // Paiements appartenant à l'utilisateur (abonnements, frais d'étude, frais de visite)
        $query = Paiement::with(['recu', 'payable'])
            ->where(function ($q) use ($user) {
                // Abonnements
                $q->whereHasMorph('payable', [\App\Models\UserAbonnement::class],
                    fn ($sub) => $sub->where('user_id', $user->id)
                )
                // Frais d'étude (bien publié par le propriétaire)
                ->orWhere(function ($sub) use ($user) {
                    $sub->where('type_paiement', 'frais_etude')
                        ->whereHasMorph('payable', [\App\Models\Bien::class],
                            fn ($b) => $b->where('user_id', $user->id)
                        );
                })
                // Frais de visite (client ayant payé pour visiter un bien)
                ->orWhere(function ($sub) use ($user) {
                    $sub->where('type_paiement', 'visite')
                        ->whereHasMorph('payable', [\App\Models\Bien::class],
                            fn ($bienQ) => $bienQ->whereHas('visites',
                                fn ($v) => $v->where('client_id', $user->id)->where('est_payee', true)
                            )
                        );
                });
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
    // GET /api/client/paiements/{id}/recu/pdf
    // Génère et télécharge le reçu en PDF
    // ─────────────────────────────────────────────────────────────────────────

    public function recuPdf(Request $request, string $id)
    {
        $user = $request->user();

        $paiement = Paiement::with(['recu', 'payable'])
            ->where(function ($q) use ($user) {
                $q->whereHasMorph('payable', [\App\Models\UserAbonnement::class],
                    fn ($sub) => $sub->where('user_id', $user->id)
                )->orWhereHasMorph('payable', [\App\Models\Bien::class],
                    fn ($sub) => $sub->where('user_id', $user->id)
                )->orWhere(function ($sub) use ($user) {
                    $sub->where('type_paiement', 'visite')
                        ->whereHasMorph('payable', [\App\Models\Bien::class],
                            fn ($bienQ) => $bienQ->whereHas('visites',
                                fn ($v) => $v->where('client_id', $user->id)->where('est_payee', true)
                            )
                        );
                });
            })
            ->findOrFail($id);

        $recu = $paiement->recu;

        if (! $recu) {
            return response()->json(['success' => false, 'message' => 'Aucun reçu disponible pour ce paiement.'], 404);
        }

        $typeLabel = match ($paiement->type_paiement) {
            'abonnement'  => 'Abonnement',
            'frais_etude' => "Frais d'étude",
            'visite'      => 'Frais de visite',
            default       => 'Paiement',
        };

        $label = match ($paiement->type_paiement) {
            'abonnement'  => $this->labelAbo($paiement),
            'frais_etude' => $this->labelFrais($paiement),
            'visite'      => $this->labelVisite($paiement),
            default       => 'Paiement',
        };

        $operateur = $paiement->operateur_paiement ?? '—';
        if ($operateur === 'CARD') $operateur = 'Carte bancaire';
        if ($operateur === 'TMONEY') $operateur = 'T-Money';
        if ($operateur === 'FLOOZ')  $operateur = 'Moov Flooz';

        $data = [
            'recu'            => $recu,
            'typeLabel'       => $typeLabel,
            'label'           => $label,
            'montant'         => (float) $paiement->montant,
            'operateur'       => $operateur,
            'reference'       => $paiement->reference_transaction ?? '',
            'nomClient'       => $user->prenom . ' ' . $user->nom,
            'emailClient'     => $user->email,
            'dateEmission'    => $recu->date_emission
                ? $recu->date_emission->format('d/m/Y à H:i')
                : now()->format('d/m/Y à H:i'),
            'datePaiement'    => $paiement->updated_at
                ? $paiement->updated_at->format('d/m/Y à H:i')
                : now()->format('d/m/Y à H:i'),
            'dateGeneration'  => now()->format('d/m/Y à H:i'),
        ];

        $pdf = Pdf::loadView('pdf.recu_paiement', $data)
            ->setPaper('a4', 'portrait');

        $filename = 'recu-' . $recu->numero_recu . '.pdf';

        return $pdf->download($filename);
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
                )->orWhere(function ($sub) use ($user) {
                    $sub->where('type_paiement', 'visite')
                        ->whereHasMorph('payable', [\App\Models\Bien::class],
                            fn ($bienQ) => $bienQ->whereHas('visites',
                                fn ($v) => $v->where('client_id', $user->id)->where('est_payee', true)
                            )
                        );
                });
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
            'visite'      => $this->labelVisite($p),
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

    private function labelVisite(Paiement $p): string
    {
        $bien = $p->payable;
        if (! $bien) return 'Frais de visite';
        return 'Frais de visite – ' . ($bien->titre ?? 'Bien');
    }

    private function buildStats($user): array
    {
        $paiementsUser = Paiement::where(function ($q) use ($user) {
            $q->whereHasMorph('payable', [\App\Models\UserAbonnement::class],
                fn ($sub) => $sub->where('user_id', $user->id)
            )->orWhereHasMorph('payable', [\App\Models\Bien::class],
                fn ($sub) => $sub->where('user_id', $user->id)
            )->orWhere(function ($sub) use ($user) {
                $sub->where('type_paiement', 'visite')
                    ->whereHasMorph('payable', [\App\Models\Bien::class],
                        fn ($bienQ) => $bienQ->whereHas('visites',
                            fn ($v) => $v->where('client_id', $user->id)->where('est_payee', true)
                        )
                    );
            });
        });

        // Les paiements de visite utilisaient 'succes' avant la correction — on inclut les deux
        $totalConfirme   = (clone $paiementsUser)->whereIn('statut', ['confirme', 'succes'])->sum('montant');
        $totalAbonnement = (clone $paiementsUser)->where('type_paiement', 'abonnement')->whereIn('statut', ['confirme', 'succes'])->sum('montant');
        $totalFraisEtude = (clone $paiementsUser)->where('type_paiement', 'frais_etude')->whereIn('statut', ['confirme', 'succes'])->sum('montant');
        $totalVisites    = (clone $paiementsUser)->where('type_paiement', 'visite')->whereIn('statut', ['confirme', 'succes'])->sum('montant');
        $nbTransactions  = (clone $paiementsUser)->whereIn('statut', ['confirme', 'succes'])->count();
        $nbEnAttente     = (clone $paiementsUser)->where('statut', 'initie')->count();

        return [
            'total_confirme'    => (float) $totalConfirme,
            'total_abonnements' => (float) $totalAbonnement,
            'total_frais_etude' => (float) $totalFraisEtude,
            'total_visites'     => (float) $totalVisites,
            'nb_transactions'   => $nbTransactions,
            'nb_en_attente'     => $nbEnAttente,
        ];
    }
}
