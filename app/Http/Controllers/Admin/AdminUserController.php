<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HistoriqueConnexion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/users/stats
    // Cards KPI en haut de la page utilisateurs
    // ─────────────────────────────────────────────────────────────────────────
    public function stats(): JsonResponse
    {
        $base = User::where('role', 'client');

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
        $query = User::where('role', 'client')
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
        $user = User::where('role', 'client')
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
        $user = User::where('role', 'client')->findOrFail($id);

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
        // Vérifier que l'utilisateur existe et est bien un client
        User::where('role', 'client')->findOrFail($id);

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
            'created_at'      => $u->created_at?->toIso8601String(),
            'updated_at'      => $u->updated_at?->toIso8601String(),
            'biens_count'     => $u->biens_count ?? 0,
        ];
    }
}
