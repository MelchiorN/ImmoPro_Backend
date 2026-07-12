<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\User;
use App\Models\Visite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
