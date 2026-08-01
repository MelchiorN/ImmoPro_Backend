<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\User;
use App\Models\Visite;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminStatsController extends Controller
{
    // ── GET /api/admin/stats ──────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $totalUsers      = User::where('role', 'client')->count();
        $totalAgents     = User::where('role', 'agent')->count();
        $bienPublies     = Bien::where('statut', 'publie')->count();
        $bienEnAttente   = Bien::where('statut', 'en_attente')->whereNull('agent_id')->count();
        $bienEnCours     = Bien::where('statut', 'en_cours')->count();
        $bienRejetes     = Bien::where('statut', 'rejete')->count();
        $visitesTotal    = Visite::count();
        $visitesPlanifiees = Visite::whereIn('statut', ['planifiee', 'confirmee'])->count();

        // Biens en attente (les 5 derniers) pour la widget "Annonces en attente"
        $biensList = Bien::with(['proprietaire', 'medias'])
            ->where('statut', 'en_attente')
            ->whereNull('agent_id')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($b) => [
                'id'       => $b->id,
                'titre'    => $b->titre,
                'adresse'  => $b->adresse,
                'photo'    => $b->medias->firstWhere('est_principale', true)?->url
                              ?? $b->medias->first()?->url,
                'client'   => $b->proprietaire
                    ? trim(($b->proprietaire->first_name ?? '') . ' ' . ($b->proprietaire->last_name ?? ''))
                    : null,
                'created_at' => $b->created_at->toIso8601String(),
            ]);

        // Performances agents (top 5 par biens traités)
        $agentsPerf = User::where('role', 'agent')
            ->withCount([
                'biensAgentAssigne as biens_publies'  => fn ($q) => $q->where('statut', 'publie'),
                'biensAgentAssigne as biens_total'    => fn ($q) => $q->whereIn('statut', ['publie', 'rejete']),
            ])
            ->orderByDesc('biens_publies')
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'id'            => $a->id,
                'name'          => trim(($a->first_name ?? '') . ' ' . ($a->last_name ?? '')),
                'initials'      => strtoupper(($a->first_name[0] ?? '') . ($a->last_name[0] ?? '')),
                'biens_total'   => $a->biens_total,
                'biens_publies' => $a->biens_publies,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'kpis' => [
                    'total_clients'      => $totalUsers,
                    'total_agents'       => $totalAgents,
                    'biens_publies'      => $bienPublies,
                    'biens_en_attente'   => $bienEnAttente,
                    'biens_en_cours'     => $bienEnCours,
                    'biens_rejetes'      => $bienRejetes,
                    'visites_total'      => $visitesTotal,
                    'visites_planifiees' => $visitesPlanifiees,
                ],
                'biens_en_attente' => $biensList,
                'agents_perf'      => $agentsPerf,
            ],
        ]);
    }

    // ── GET /api/admin/stats/charts ───────────────────────────────────────────
    public function charts(Request $request): JsonResponse
    {
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(\Carbon\Carbon::now()->startOfMonth()->subMonths($i));
        }

        // Soumissions par mois
        $soumis = Bien::select(
                \Illuminate\Support\Facades\DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mois"),
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', \Carbon\Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->pluck('total', 'mois');

        // Publications par mois
        $publies = Bien::select(
                \Illuminate\Support\Facades\DB::raw("DATE_FORMAT(updated_at, '%Y-%m') as mois"),
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total')
            )
            ->where('statut', 'publie')
            ->where('updated_at', '>=', \Carbon\Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->pluck('total', 'mois');

        // Rejets par mois
        $rejetes = Bien::select(
                \Illuminate\Support\Facades\DB::raw("DATE_FORMAT(updated_at, '%Y-%m') as mois"),
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total')
            )
            ->where('statut', 'rejete')
            ->where('updated_at', '>=', \Carbon\Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->pluck('total', 'mois');

        // Inscriptions clients par mois
        $inscriptions = User::select(
                \Illuminate\Support\Facades\DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mois"),
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total')
            )
            ->where('role', 'client')
            ->where('created_at', '>=', \Carbon\Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->pluck('total', 'mois');

        $labels       = [];
        $dataSoum     = [];
        $dataPub      = [];
        $dataRejets   = [];
        $dataInscr    = [];

        foreach ($months as $date) {
            $key = $date->format('Y-m');
            $labels[]     = $date->locale('fr')->isoFormat('MMM YY');
            $dataSoum[]   = $soumissions[$key]  ?? 0;
            $dataPub[]    = $publications[$key] ?? 0;
            $dataRejets[] = $rejets[$key]        ?? 0;
            $dataInscr[]  = $inscriptions[$key]  ?? 0;
        }

        // Répartition par type de bien (top 6)
        $parTypeBien = Bien::select('type_bien', DB::raw('COUNT(*) as total'))
            ->groupBy('type_bien')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'type_bien');

        // Localisation des biens publiés (pour la carte)
        $biensCarte = Bien::select('id', 'titre', 'adresse', 'latitude', 'longitude', 'type_bien', 'prix', 'unite_prix')
            ->where('statut', 'publie')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest('publie_le')
            ->limit(200)
            ->get()
            ->map(fn ($b) => [
                'id'        => $b->id,
                'titre'     => $b->titre,
                'adresse'   => $b->adresse,
                'lat'       => (float) $b->latitude,
                'lng'       => (float) $b->longitude,
                'type_bien' => $b->type_bien,
                'prix'      => $b->prix,
                'unite_prix'=> $b->unite_prix,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'labels'       => $labels,
                'soumissions'  => $dataSoum,
                'publications' => $dataPub,
                'rejets'       => $dataRejets,
                'inscriptions' => $dataInscr,
                'par_type_bien'=> $parTypeBien,
                'biens_carte'  => $biensCarte,
            ],
        ]);
    }
}
