<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\HistoriqueConnexion;
use App\Models\Rapport;
use App\Models\User;
use App\Models\UserAbonnement;
use App\Models\Visite;
use App\Models\Favori;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class AdminUserController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/users/stats
    // Cards KPI en haut de la page utilisateurs
    // ─────────────────────────────────────────────────────────────────────────
    public function stats(): JsonResponse
    {
        $base = User::whereIn('role', ['client', 'admin', 'agent']);

        return response()->json([
            'success' => true,
            'data'    => [
                'total'     => (clone $base)->count(),
                'actifs'    => (clone $base)->where('status', 'active')->count(),
                'suspendus' => (clone $base)->where('status', 'suspended')->count(),
                'bloques'   => (clone $base)->where('status', 'blocked')->count(),
                'nouveaux_ce_mois' => (clone $base)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/users
    // Liste paginée des clients avec filtres
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = User::whereIn('role', ['client', 'admin', 'agent'])
            ->withCount('biens')
            ->latest();

        // Recherche full-text
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name',  'like', "%{$search}%")
                  ->orWhere('email',      'like', "%{$search}%")
                  ->orWhere('telephone',  'like', "%{$search}%");
            });
        }

        // Filtre par statut
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Filtre par rôle
        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        $users = $query->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $users->getCollection()->map(fn ($u) => $this->formatUser($u))->values(),
            'meta'    => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/users/{id}
    // Détail complet d'un utilisateur client
    // ─────────────────────────────────────────────────────────────────────────
    public function show(string $id): JsonResponse
    {
        $user = User::whereIn('role', ['client', 'admin', 'agent'])
            ->withCount('biens')
            ->findOrFail($id);

        // Dernière connexion
        $derniereConnexion = HistoriqueConnexion::where('user_id', $user->id)
            ->where('statut', 'succes')
            ->latest('connected_at')
            ->first();

        $data = $this->formatUser($user);
        $data['derniere_connexion'] = $derniereConnexion?->connected_at?->toIso8601String();
        $data['device_type']        = $derniereConnexion?->device_type;
        $data['plateforme']         = $derniereConnexion?->plateforme;
        $data['ville_connexion']    = $derniereConnexion?->ville;
        $data['pays_connexion']     = $derniereConnexion?->pays;

        // Stats biens par statut
        $data['biens_stats'] = [
            'total'      => $user->biens()->count(),
            'en_attente' => $user->biens()->where('statut', 'en_attente')->count(),
            'en_cours'   => $user->biens()->where('statut', 'en_cours')->count(),
            'publie'     => $user->biens()->where('statut', 'publie')->count(),
            'rejete'     => $user->biens()->where('statut', 'rejete')->count(),
        ];

        // Derniers biens soumis (3 max)
        if ($user->role === 'client') {
            $data['derniers_biens'] = $user->biens()
                ->with('medias')
                ->latest()
                ->limit(3)
                ->get()
                ->map(fn ($b) => [
                    'id'     => $b->id,
                    'titre'  => $b->titre,
                    'statut' => $b->statut,
                    'photo'  => $b->medias->firstWhere('est_principale', true)?->url
                                ?? $b->medias->first()?->url,
                    'created_at' => $b->created_at->toIso8601String(),
                ]);
        } else {
            $data['derniers_biens'] = [];
        }

        $data['activity_log'] = Activity::with(['subject'])
            ->where('causer_type', 'App\\Models\\User')
            ->where('causer_id', $user->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($activity) => [
                'id'            => $activity->id,
                'description'   => $activity->description,
                'log_name'      => $activity->log_name,
                'subject_type'  => $activity->subject_type ? class_basename($activity->subject_type) : null,
                'subject_label' => $activity->subject ? (
                    $activity->subject->titre
                    ?? $activity->subject->email
                    ?? $activity->subject->first_name . ' ' . ($activity->subject->last_name ?? '')
                ) : null,
                'properties'    => $activity->properties,
                'created_at'    => $activity->created_at?->toIso8601String(),
            ]);

        // Historique de connexions (30 dernières sessions)
        $data['connexions'] = HistoriqueConnexion::where('user_id', $user->id)
            ->latest('connected_at')
            ->limit(30)
            ->get()
            ->map(fn ($h) => [
                'id'           => $h->id,
                'ip_address'   => $h->ip_address,
                'device_type'  => $h->device_type,
                'plateforme'   => $h->plateforme,
                'ville'        => $h->ville,
                'pays'         => $h->pays,
                'statut'       => $h->statut,
                'connected_at' => $h->connected_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/admin/users/{id}/status
    // Activer / suspendre / bloquer un compte client
    // ─────────────────────────────────────────────────────────────────────────
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $user = User::whereIn('role', ['client', 'admin', 'agent'])->findOrFail($id);

        $request->validate([
            'status' => 'required|in:active,suspended,blocked',
        ]);

        $user->update(['status' => $request->status]);

        // Log d'activité
        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties(['ancien_status' => $user->getOriginal('status'), 'nouveau_status' => $request->status])
            ->log("Statut du compte client modifié : {$request->status}");

        $labels = [
            'active'    => 'activé',
            'suspended' => 'suspendu',
            'blocked'   => 'bloqué',
        ];

        return response()->json([
            'success' => true,
            'message' => "Compte utilisateur {$labels[$request->status]} avec succès.",
            'data'    => $this->formatUser($user->fresh()),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/users/{id}/historique
    // Historique des connexions d'un utilisateur (paginé)
    // ─────────────────────────────────────────────────────────────────────────
    public function historique(Request $request, string $id): JsonResponse
    {
        // Vérifier que l'utilisateur existe et est bien un compte administré
        User::whereIn('role', ['client', 'admin', 'agent'])->findOrFail($id);

        $historique = HistoriqueConnexion::where('user_id', $id)
            ->latest('connected_at')
            ->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $historique->getCollection()->map(fn ($h) => [
                'id'           => $h->id,
                'ip_address'   => $h->ip_address,
                'device_type'  => $h->device_type,
                'plateforme'   => $h->plateforme,
                'ville'        => $h->ville,
                'pays'         => $h->pays,
                'statut'       => $h->statut,
                'connected_at' => $h->connected_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'total'        => $historique->total(),
                'per_page'     => $historique->perPage(),
                'current_page' => $historique->currentPage(),
                'last_page'    => $historique->lastPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/users/{id}/stats/agent
    // Statistiques d'un agent spécifique (vu par l'admin)
    // ─────────────────────────────────────────────────────────────────────────
    public function agentStats(string $id): JsonResponse
    {
        $agent = User::where('role', 'agent')->findOrFail($id);

        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(Carbon::now()->startOfMonth()->subMonths($i));
        }

        // Biens publiés/validés par mois
        $publies = Bien::select(
                DB::raw("DATE_FORMAT(updated_at, '%Y-%m') as mois"),
                DB::raw('COUNT(*) as total')
            )
            ->where('agent_id', $agent->id)
            ->whereIn('statut', ['valide', 'publie'])
            ->where('updated_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->pluck('total', 'mois');

        // Biens rejetés par mois
        $rejetes = Bien::select(
                DB::raw("DATE_FORMAT(updated_at, '%Y-%m') as mois"),
                DB::raw('COUNT(*) as total')
            )
            ->where('agent_id', $agent->id)
            ->where('statut', 'rejete')
            ->where('updated_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->pluck('total', 'mois');

        // Visites planifiées par mois
        $visites = Visite::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mois"),
                DB::raw('COUNT(*) as total')
            )
            ->where('agent_id', $agent->id)
            ->where('created_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->pluck('total', 'mois');

        // Rapports rédigés par mois
        $rapports = Rapport::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mois"),
                DB::raw('COUNT(*) as total')
            )
            ->where('agent_id', $agent->id)
            ->where('created_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->groupBy('mois')
            ->pluck('total', 'mois');

        $labels = []; $dataPub = []; $dataRej = []; $dataVis = []; $dataRap = [];
        foreach ($months as $date) {
            $key = $date->format('Y-m');
            $labels[]  = $date->locale('fr')->isoFormat('MMM YY');
            $dataPub[] = $publies[$key]  ?? 0;
            $dataRej[] = $rejetes[$key]  ?? 0;
            $dataVis[] = $visites[$key]  ?? 0;
            $dataRap[] = $rapports[$key] ?? 0;
        }

        // Répartition biens par type
        $parType = Bien::select('type_bien', DB::raw('COUNT(*) as total'))
            ->where('agent_id', $agent->id)
            ->groupBy('type_bien')
            ->orderByDesc('total')
            ->pluck('total', 'type_bien');

        // KPI totaux
        $kpis = [
            'biens_traites'  => Bien::where('agent_id', $agent->id)->count(),
            'biens_publies'  => Bien::where('agent_id', $agent->id)->whereIn('statut', ['publie', 'valide'])->count(),
            'biens_rejetes'  => Bien::where('agent_id', $agent->id)->where('statut', 'rejete')->count(),
            'visites_total'  => Visite::where('agent_id', $agent->id)->count(),
            'rapports_total' => Rapport::where('agent_id', $agent->id)->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'kpis'     => $kpis,
                'labels'   => $labels,
                'publies'  => $dataPub,
                'rejetes'  => $dataRej,
                'visites'  => $dataVis,
                'rapports' => $dataRap,
                'par_type' => $parType,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/users/{id}/stats/client
    // Statistiques d'un client/propriétaire spécifique (vu par l'admin)
    // ─────────────────────────────────────────────────────────────────────────
    public function clientStats(string $id): JsonResponse
    {
        $client = User::whereIn('role', ['client', 'admin'])->findOrFail($id);
        $userId = $client->id;

        // Biens par statut
        $biensParStatut = Bien::where('user_id', $userId)
            ->selectRaw('statut, COUNT(*) as count')
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->toArray();

        // Évolution publications (12 mois)
        $evolutionPublications = Bien::where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, COUNT(*) as count')
            ->groupBy('mois')
            ->orderBy('mois')
            ->get()
            ->map(fn ($item) => ['mois' => $item->mois, 'count' => $item->count])
            ->toArray();

        // Visites effectuées (client demandeur)
        $visitesParMois = Visite::where('client_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mois, COUNT(*) as count')
            ->groupBy('mois')
            ->orderBy('mois')
            ->get()
            ->map(fn ($item) => ['mois' => $item->mois, 'count' => $item->count])
            ->toArray();

        $visitesTotal  = Visite::where('client_id', $userId)->count();
        $visitesPayees = Visite::where('client_id', $userId)->where('statut', 'payee')->count();

        // Abonnements
        $abonnementsCount = UserAbonnement::where('user_id', $userId)->count();
        $abonnementActif  = UserAbonnement::where('user_id', $userId)
            ->where('statut', 'actif')
            ->with('plan')
            ->first();

        // Favoris
        $totalFavoris = \App\Models\Favori::where('user_id', $userId)->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'biens_par_statut' => [
                    'total'      => array_sum($biensParStatut),
                    'publie'     => $biensParStatut['publie']     ?? 0,
                    'en_attente' => $biensParStatut['en_attente'] ?? 0,
                    'rejete'     => $biensParStatut['rejete']     ?? 0,
                    'retire'     => $biensParStatut['retire']     ?? 0,
                    'brouillon'  => $biensParStatut['brouillon']  ?? 0,
                ],
                'evolution_publications' => $evolutionPublications,
                'visites' => [
                    'total'     => $visitesTotal,
                    'payees'    => $visitesPayees,
                    'par_mois'  => $visitesParMois,
                ],
                'abonnements' => [
                    'total'  => $abonnementsCount,
                    'actif'  => $abonnementActif ? [
                        'plan'  => $abonnementActif->plan->nom ?? 'Inconnu',
                        'quota' => $abonnementActif->nb_publications_restantes,
                    ] : null,
                ],
                'favoris_total' => $totalFavoris,
            ],
        ]);
    }

    // ─── Helper format ────────────────────────────────────────────────────────
    private function formatUser(User $u): array
    {
        return [
            'id'              => $u->id,
            'first_name'      => $u->first_name,
            'last_name'       => $u->last_name,
            'email'           => $u->email,
            'telephone'       => $u->telephone,
            'country'         => $u->country,
            'city'            => $u->city,
            'profile_picture' => $u->profile_picture,
            'status'          => $u->status,
            'role'            => $u->role,
            'created_at'      => $u->created_at?->toIso8601String(),
            'updated_at'      => $u->updated_at?->toIso8601String(),
            'biens_count'     => $u->biens_count ?? 0,
        ];
    }
}
