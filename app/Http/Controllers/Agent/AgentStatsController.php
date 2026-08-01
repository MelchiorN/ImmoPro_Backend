<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Rapport;
use App\Models\Visite;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentStatsController extends Controller
{
    // ── GET /api/agent/stats/charts ───────────────────────────────────────────
    // Données temporelles sur les 6 derniers mois pour les graphiques de l'agent
    public function charts(Request $request): JsonResponse
    {
        $agentId = $request->user()->id;

        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(Carbon::now()->startOfMonth()->subMonths($i));
        }

        // Biens traités (publiés) par mois
        $publies = Bien::select(
                DB::raw("DATE_FORMAT(updated_at, '%Y-%m') as mois"),
                DB::raw('COUNT(*) as total')
            )
            ->where('agent_id', $agentId)
            ->whereIn('statut', ['valide', 'publie'])
            ->where('updated_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->pluck('total', 'mois');

        // Biens rejetés par mois
        $rejetes = Bien::select(
                DB::raw("DATE_FORMAT(updated_at, '%Y-%m') as mois"),
                DB::raw('COUNT(*) as total')
            )
            ->where('agent_id', $agentId)
            ->where('statut', 'rejete')
            ->where('updated_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->pluck('total', 'mois');

        // Visites planifiées par mois
        $visites = Visite::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mois"),
                DB::raw('COUNT(*) as total')
            )
            ->where('agent_id', $agentId)
            ->where('created_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->pluck('total', 'mois');

        // Rapports rédigés par mois (tous statuts : brouillon, valide, rejete)
        $rapports = Rapport::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mois"),
                DB::raw('COUNT(*) as total')
            )
            ->where('agent_id', $agentId)
            ->whereIn('statut', [Rapport::STATUT_BROUILLON, Rapport::STATUT_VALIDE, Rapport::STATUT_REJETE])
            ->where('created_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->pluck('total', 'mois');

        // Construire les séries complètes
        $labels      = [];
        $dataPub     = [];
        $dataRej     = [];
        $dataVis     = [];
        $dataRap     = [];

        foreach ($months as $date) {
            $key = $date->format('Y-m');
            $labels[]  = $date->locale('fr')->isoFormat('MMM YY');
            $dataPub[] = $publies[$key]  ?? 0;
            $dataRej[] = $rejetes[$key]  ?? 0;
            $dataVis[] = $visites[$key]  ?? 0;
            $dataRap[] = $rapports[$key] ?? 0;
        }

        // Répartition des biens de l'agent par type
        $parType = Bien::select('type_bien', DB::raw('COUNT(*) as total'))
            ->where('agent_id', $agentId)
            ->groupBy('type_bien')
            ->orderByDesc('total')
            ->pluck('total', 'type_bien');

        // Localisation des biens publiés assignés à cet agent (pour carte)
        $biensCarte = Bien::select('id', 'titre', 'adresse', 'latitude', 'longitude', 'type_bien', 'statut')
            ->where('agent_id', $agentId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn ($b) => [
                'id'        => $b->id,
                'titre'     => $b->titre,
                'adresse'   => $b->adresse,
                'lat'       => (float) $b->latitude,
                'lng'       => (float) $b->longitude,
                'type_bien' => $b->type_bien,
                'statut'    => $b->statut,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'labels'      => $labels,
                'publies'     => $dataPub,
                'rejetes'     => $dataRej,
                'visites'     => $dataVis,
                'rapports'    => $dataRap,
                'par_type'    => $parType,
                'biens_carte' => $biensCarte,
            ],
        ]);
    }
}
