<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Favori;
use App\Models\UserAbonnement;
use App\Models\Visite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    /**
     * Statistiques globales pour le client/propriétaire
     * Retourne à la fois stats propriétaire et client
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json([
            'success' => true,
            'data' => [
                'proprietaire' => $this->getProprietaireStats($userId),
                'client' => $this->getClientStats($userId),
            ],
        ]);
    }

    /**
     * Statistiques en tant que PROPRIÉTAIRE
     */
    public function proprietaire(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json([
            'success' => true,
            'data' => $this->getProprietaireStats($userId),
        ]);
    }

    /**
     * Statistiques en tant que CLIENT
     */
    public function client(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json([
            'success' => true,
            'data' => $this->getClientStats($userId),
        ]);
    }

    /**
     * Calcul des stats propriétaire
     */
    private function getProprietaireStats(int $userId): array
    {
        // ─────────────────────────────────────────────────────────────────────
        // 1. Répartition des biens par statut
        // ─────────────────────────────────────────────────────────────────────
        $biensParStatut = Bien::where('user_id', $userId)
            ->selectRaw('statut, COUNT(*) as count')
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->toArray();

        $totalBiens = array_sum($biensParStatut);

        // ─────────────────────────────────────────────────────────────────────
        // 2. Évolution des publications par mois (12 derniers mois)
        // ─────────────────────────────────────────────────────────────────────
        $evolutionPublications = Bien::where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, COUNT(*) as count')
            ->groupBy('mois')
            ->orderBy('mois', 'asc')
            ->get()
            ->map(fn($item) => [
                'mois' => $item->mois,
                'count' => $item->count,
            ])
            ->toArray();

        // ─────────────────────────────────────────────────────────────────────
        // 3. État de l'abonnement actif
        // ─────────────────────────────────────────────────────────────────────
        $abonnementActif = UserAbonnement::where('user_id', $userId)
            ->where('statut', 'actif')
            ->with('plan')
            ->first();

        $abonnementData = $abonnementActif
            ? [
                'actif' => true,
                'plan' => $abonnementActif->plan->nom ?? 'Inconnu',
                'date_fin' => null,
                'publications_restantes' => $abonnementActif->nb_publications_restantes,
            ]
            : [
                'actif' => false,
                'plan' => null,
                'date_fin' => null,
                'publications_restantes' => 0,
            ];

        // ─────────────────────────────────────────────────────────────────────
        // 4. Visites reçues sur mes biens (par mois, 12 derniers mois)
        // ─────────────────────────────────────────────────────────────────────
        $mesBiensIds = Bien::where('user_id', $userId)->pluck('id')->toArray();

        $visitesRecuesParMois = Visite::whereIn('bien_id', $mesBiensIds)
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, COUNT(*) as count')
            ->groupBy('mois')
            ->orderBy('mois', 'asc')
            ->get()
            ->map(fn($item) => [
                'mois' => $item->mois,
                'count' => $item->count,
            ])
            ->toArray();

        $totalVisitesRecues = Visite::whereIn('bien_id', $mesBiensIds)->count();

        return [
            'biens_par_statut' => [
                'total' => $totalBiens,
                'publie' => $biensParStatut['publie'] ?? 0,
                'en_attente' => $biensParStatut['en_attente'] ?? 0,
                'en_verification' => $biensParStatut['en_verification'] ?? 0,
                'rejete' => $biensParStatut['rejete'] ?? 0,
                'valide' => $biensParStatut['valide'] ?? 0,
                'archive' => $biensParStatut['archive'] ?? 0,
                'brouillon' => $biensParStatut['brouillon'] ?? 0,
            ],
            'evolution_publications' => $evolutionPublications,
            'abonnement' => $abonnementData,
            'visites_recues' => [
                'total' => $totalVisitesRecues,
                'par_mois' => $visitesRecuesParMois,
            ],
        ];
    }

    /**
     * Calcul des stats client
     */
    private function getClientStats(int $userId): array
    {
        // ─────────────────────────────────────────────────────────────────────
        // 1. Mes visites effectuées (par mois, 12 derniers mois)
        // ─────────────────────────────────────────────────────────────────────
        $visitesParMois = Visite::where('client_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, COUNT(*) as count')
            ->groupBy('mois')
            ->orderBy('mois', 'asc')
            ->get()
            ->map(fn($item) => [
                'mois' => $item->mois,
                'count' => $item->count,
            ])
            ->toArray();

        // ─────────────────────────────────────────────────────────────────────
        // 2. Statuts des visites
        // ─────────────────────────────────────────────────────────────────────
        $visitesParStatut = Visite::where('client_id', $userId)
            ->selectRaw('statut, COUNT(*) as count')
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->toArray();

        $totalVisites = array_sum($visitesParStatut);

        // ─────────────────────────────────────────────────────────────────────
        // 3. Favoris ajoutés (par mois, 12 derniers mois)
        // ─────────────────────────────────────────────────────────────────────
        $favorisParMois = Favori::where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, COUNT(*) as count')
            ->groupBy('mois')
            ->orderBy('mois', 'asc')
            ->get()
            ->map(fn($item) => [
                'mois' => $item->mois,
                'count' => $item->count,
            ])
            ->toArray();

        $totalFavoris = Favori::where('user_id', $userId)->count();

        // ─────────────────────────────────────────────────────────────────────
        // 4. Abonnement actif (même calcul que propriétaire)
        // ─────────────────────────────────────────────────────────────────────
        $abonnementActif = UserAbonnement::where('user_id', $userId)
            ->where('statut', 'actif')
            ->with('plan')
            ->first();

        $abonnementData = $abonnementActif
            ? [
                'actif' => true,
                'plan' => $abonnementActif->plan->nom ?? 'Inconnu',
                'date_fin' => null,
            ]
            : [
                'actif' => false,
                'plan' => null,
                'date_fin' => null,
            ];

        return [
            'visites_effectuees' => [
                'total' => $totalVisites,
                'par_mois' => $visitesParMois,
            ],
            'visites_par_statut' => [
                'confirmee' => $visitesParStatut['confirmee'] ?? 0,
                'en_attente' => $visitesParStatut['en_attente'] ?? 0,
                'payee' => $visitesParStatut['payee'] ?? 0,
                'refusee' => $visitesParStatut['refusee'] ?? 0,
                'annulee' => $visitesParStatut['annulee'] ?? 0,
            ],
            'favoris' => [
                'total' => $totalFavoris,
                'par_mois' => $favorisParMois,
            ],
            'abonnement' => $abonnementData,
        ];
    }
}
