<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Recu;
use App\Models\UserAbonnement;
use App\Models\Bien;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminTransactionController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/transactions
    // Liste paginée de tous les paiements (abonnement, frais_etude, location)
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $type    = $request->query('type');    // abonnement | frais_etude | location
        $statut  = $request->query('statut');  // initie | confirme | echoue
        $search  = $request->query('search');  // référence / nom

        $query = Paiement::with([
            'recu',
            'payable',
            'location.bien',
            'location.locataire',
        ])->latest();

        if ($type) {
            $query->where('type_paiement', $type);
        }

        if ($statut) {
            $query->where('statut', $statut);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_transaction', 'like', "%{$search}%")
                  ->orWhere('semoa_bill_id', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(fn ($p) => $this->formatPaiement($p));

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/transactions/stats
    // Totaux par type et statut pour les KPI du dashboard
    // ─────────────────────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $totalConfirme = Paiement::where('statut', 'confirme')->sum('montant');
        $totalInitie   = Paiement::where('statut', 'initie')->sum('montant');
        $totalEchoue   = Paiement::where('statut', 'echoue')->sum('montant');

        $byType = Paiement::selectRaw('type_paiement, statut, COUNT(*) as nb, SUM(montant) as total')
            ->groupBy('type_paiement', 'statut')
            ->get()
            ->groupBy('type_paiement');

        return response()->json([
            'success' => true,
            'data'    => [
                'total_confirme' => (float) $totalConfirme,
                'total_initie'   => (float) $totalInitie,
                'total_echoue'   => (float) $totalEchoue,
                'by_type'        => $byType,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/transactions/{id}/recu
    // Télécharger ou afficher le reçu d'un paiement
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadRecu(string $id): mixed
    {
        $paiement = Paiement::with('recu')->findOrFail($id);
        $recu     = $paiement->recu;

        if (! $recu) {
            return response()->json(['success' => false, 'message' => 'Aucun reçu disponible.'], 404);
        }

        // Si un PDF existe sur le disque, le streamer
        if ($recu->fichier_pdf && Storage::disk('local')->exists($recu->fichier_pdf)) {
            return Storage::disk('local')->download(
                $recu->fichier_pdf,
                "recu-{$recu->numero_recu}.pdf",
                ['Content-Type' => 'application/pdf']
            );
        }

        // Sinon retourner les données JSON du reçu
        return response()->json([
            'success' => true,
            'data'    => [
                'numero_recu'        => $recu->numero_recu,
                'date_emission'      => $recu->date_emission,
                'montant'            => $paiement->montant,
                'operateur_paiement' => $paiement->operateur_paiement,
                'reference'          => $paiement->reference_transaction,
                'type_paiement'      => $paiement->type_paiement,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Formateur interne
    // ─────────────────────────────────────────────────────────────────────────

    private function formatPaiement(Paiement $p): array
    {
        // Résoudre le nom/label du payable selon le type
        $payableLabel = match ($p->type_paiement) {
            'abonnement' => $this->labelAbonnement($p),
            'frais_etude' => $this->labelFraisEtude($p),
            'location'   => $this->labelLocation($p),
            default      => 'Inconnu',
        };

        return [
            'id'                    => $p->id,
            'type_paiement'         => $p->type_paiement,
            'montant'               => (float) $p->montant,
            'operateur_paiement'    => $p->operateur_paiement,
            'statut'                => $p->statut,
            'reference_transaction' => $p->reference_transaction,
            'semoa_bill_id'         => $p->semoa_bill_id,
            'created_at'            => $p->created_at?->toIso8601String(),
            'payable_label'         => $payableLabel,
            'recu'                  => $p->recu ? [
                'id'          => $p->recu->id,
                'numero_recu' => $p->recu->numero_recu,
                'date_emission' => $p->recu->date_emission,
                'has_pdf'     => $p->recu->fichier_pdf && Storage::disk('local')->exists($p->recu->fichier_pdf),
            ] : null,
        ];
    }

    private function labelAbonnement(Paiement $p): string
    {
        $abo = $p->payable;
        if (! $abo) return 'Abonnement #' . substr($p->id, 0, 8);
        $plan = method_exists($abo, 'plan') ? $abo->plan : null;
        $user = method_exists($abo, 'user') ? $abo->user : null;
        $nomPlan = $plan?->nom ?? 'Plan';
        $nomUser = $user ? trim(($user->prenom ?? '') . ' ' . ($user->nom ?? '')) : 'Client';
        return "Abonnement «{$nomPlan}» – {$nomUser}";
    }

    private function labelFraisEtude(Paiement $p): string
    {
        $bien = $p->payable;
        if (! $bien) return 'Frais d\'étude #' . substr($p->id, 0, 8);
        $user = method_exists($bien, 'proprietaire') ? $bien->proprietaire : null;
        $nomUser = $user ? trim(($user->prenom ?? '') . ' ' . ($user->nom ?? '')) : 'Client';
        return "Frais d'étude «" . ($bien->titre ?? 'Bien') . "» – {$nomUser}";
    }

    private function labelLocation(Paiement $p): string
    {
        $loc = $p->location;
        if (! $loc) return 'Location #' . substr($p->id, 0, 8);
        $adresse = $loc->bien?->adresse ?? 'Bien';
        $locataire = $loc->locataire ? trim(($loc->locataire->prenom ?? '') . ' ' . ($loc->locataire->nom ?? '')) : 'Locataire';
        return "Location «{$adresse}» – {$locataire}";
    }
}
