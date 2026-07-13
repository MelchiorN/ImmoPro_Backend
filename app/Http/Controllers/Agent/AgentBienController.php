<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Resources\BienListResource;
use App\Http\Resources\BienResource;
use App\Models\Bien;
use App\Models\DocumentBien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AgentBienController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/biens
    // Pool de biens accessibles à l'agent connecté :
    //   - statut=en_attente  + agent_id IS NULL  → onglet "Non assignés"
    //   - statut=en_cours    + agent_id = moi    → onglet "En cours"
    //   - statut=publie|rejete + agent_id = moi  → onglet "Terminés"
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $agentId   = $request->user()->id;
        $onglet    = $request->query('onglet', 'non_assigne'); // non_assigne | en_cours | termine

        $query = Bien::with(['medias', 'proprietaire', 'rapport'])
            ->when(
                $request->query('type_bien'),
                fn ($q, $t) => $q->where('type_bien', $t)
            )
            ->when(
                $request->query('priorite'),
                fn ($q, $p) => $q->where('priorite', $p)
            )
            ->when(
                $request->query('search'),
                fn ($q, $s) => $q->where(function ($sq) use ($s) {
                    $sq->where('titre', 'like', "%{$s}%")
                       ->orWhere('adresse', 'like', "%{$s}%");
                })
            );

        switch ($onglet) {
            case 'non_assigne':
                // Tous les biens en attente sans agent assigné
                $query->where('statut', 'en_attente')
                      ->whereNull('agent_id');
                break;

            case 'en_cours':
                // Biens pris en charge par cet agent (statut en_cours)
                $query->where('statut', 'en_cours')
                      ->where('agent_id', $agentId);
                break;

            case 'termine':
                // Biens publiés ou rejetés par cet agent
                $query->whereIn('statut', ['publie', 'rejete', 'archive'])
                      ->where('agent_id', $agentId);
                break;

            default:
                $query->where('statut', 'en_attente')
                      ->whereNull('agent_id');
        }

        $biens = $query->latest()->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => BienListResource::collection($biens->items()),
            'meta'    => [
                'total'        => $biens->total(),
                'per_page'     => $biens->perPage(),
                'current_page' => $biens->currentPage(),
                'last_page'    => $biens->lastPage(),
                'onglet'       => $onglet,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/stats
    // Dashboard : KPIs + biens en cours + visites à venir
    // ─────────────────────────────────────────────────────────────────────────

    public function stats(Request $request): JsonResponse
    {
        $agentId = $request->user()->id;

        // ── Compteurs ─────────────────────────────────────────────────────────
        $nonAssigne  = Bien::where('statut', 'en_attente')->whereNull('agent_id')->count();
        $enCours     = Bien::where('statut', 'en_cours')->where('agent_id', $agentId)->count();
        $publies     = Bien::where('statut', 'publie')->where('agent_id', $agentId)->count();
        $rejetes     = Bien::where('statut', 'rejete')->where('agent_id', $agentId)->count();
        $totalTraite = $publies + $rejetes;
        $tauxValid   = $totalTraite > 0 ? round($publies / $totalTraite * 100) : null;

        // Visites
        $visitesTotal     = \App\Models\Visite::where('agent_id', $agentId)->count();
        $visitesPlanifiees = \App\Models\Visite::where('agent_id', $agentId)
            ->whereIn('statut', ['planifiee', 'confirmee'])
            ->where('date_visite', '>=', now())
            ->count();

        // Prochaine visite
        $prochaineVisite = \App\Models\Visite::with(['bien'])
            ->where('agent_id', $agentId)
            ->whereIn('statut', ['planifiee', 'confirmee'])
            ->where('date_visite', '>=', now())
            ->orderBy('date_visite')
            ->first();

        $prochaineHeure = $prochaineVisite?->date_visite
            ? \Carbon\Carbon::parse($prochaineVisite->date_visite)->format('H\hi')
            : null;

        // Rapports brouillons
        $rapportsBrouillons = \App\Models\Rapport::where('agent_id', $agentId)
            ->where('statut', 'brouillon')
            ->count();

        // ── Biens en cours récents (tableau) ──────────────────────────────────
        $biensEnCours = Bien::with(['medias', 'proprietaire'])
            ->where('statut', 'en_cours')
            ->where('agent_id', $agentId)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($b) => [
                'id'      => $b->id,
                'titre'   => $b->titre,
                'adresse' => $b->adresse,
                'photo'   => $b->medias?->firstWhere('est_principale', true)?->url
                             ?? $b->medias?->first()?->url,
                'client'  => $b->proprietaire ? [
                    'nom'   => trim(($b->proprietaire->first_name ?? '') . ' ' . ($b->proprietaire->last_name ?? '')),
                    'email' => $b->proprietaire->email,
                ] : null,
                'priorite'   => $b->priorite,
                'created_at' => $b->created_at?->toIso8601String(),
            ]);

        // ── Visites à venir (mini-calendrier) ─────────────────────────────────
        $upcomingVisites = \App\Models\Visite::with(['bien'])
            ->where('agent_id', $agentId)
            ->whereIn('statut', ['planifiee', 'confirmee'])
            ->where('date_visite', '>=', now())
            ->orderBy('date_visite')
            ->limit(5)
            ->get()
            ->map(fn ($v) => [
                'id'           => $v->id,
                'date_visite'  => $v->date_visite?->toIso8601String(),
                'statut'       => $v->statut,
                'bien_titre'   => $v->bien?->titre,
                'bien_adresse' => $v->bien?->adresse,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'kpis' => [
                    'non_assigne'        => $nonAssigne,
                    'en_cours'           => $enCours,
                    'publies'            => $publies,
                    'visites_planifiees' => $visitesPlanifiees,
                    'taux_validation'    => $tauxValid,
                    'prochaine_visite'   => $prochaineHeure,
                    'rapports_brouillons'=> $rapportsBrouillons,
                ],
                'biens_en_cours'   => $biensEnCours,
                'upcoming_visites' => $upcomingVisites,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/biens/{id}
    // Détail complet d'un bien (accessible si non-assigné ou assigné à moi)
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->id;

        $bien = Bien::with(['medias', 'documents', 'proprietaire'])
            ->where(function ($q) use ($agentId) {
                // Accessible si non assigné ou assigné à moi
                $q->whereNull('agent_id')
                  ->orWhere('agent_id', $agentId);
            })
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => new BienResource($bien),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/agent/biens/{id}/claim
    // L'agent prend en charge un bien non assigné → statut passe à "en_cours"
    // ─────────────────────────────────────────────────────────────────────────

    public function claim(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->id;

        // Transaction + verrou pour éviter la race condition
        $updated = DB::transaction(function () use ($id, $agentId) {
            // Chercher le bien non-assigné et le verrouiller
            $bien = Bien::where('id', $id)
                        ->where('statut', 'en_attente')
                        ->whereNull('agent_id')
                        ->lockForUpdate()
                        ->first();

            if (! $bien) {
                return null; // déjà pris ou inexistant
            }

            $bien->update([
                'agent_id' => $agentId,
                'statut'   => 'en_cours',
            ]);
            return $bien;
        });

        if (! $updated) {
            return response()->json([
                'success' => false,
                'message' => 'Ce bien est déjà pris en charge par un autre agent, ou n\'existe pas.',
            ], 409); // Conflict
        }

        return response()->json([
            'success' => true,
            'message' => 'Bien pris en charge. Vous pouvez maintenant rédiger votre rapport.',
            'data'    => new BienResource($updated->fresh(['medias', 'documents', 'proprietaire'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/agent/biens/{id}/release  — DÉSACTIVÉ
    // Un agent ne peut plus libérer un bien une fois pris en charge.
    // ─────────────────────────────────────────────────────────────────────────

    public function release(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Un bien pris en charge ne peut plus être libéré. Contactez un administrateur si nécessaire.',
        ], 403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/agent/biens/{id}/statut
    // L'agent publie ou rejette un bien qu'il a pris en charge
    // ─────────────────────────────────────────────────────────────────────────

    public function updateStatut(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'statut'     => 'required|in:publie,rejete',
            'note_admin' => 'nullable|string|max:1000',
        ]);

        $agentId = $request->user()->id;

        // L'agent ne peut agir que sur les biens qu'il a pris en charge (statut en_cours)
        $bien = Bien::where('id', $id)
                    ->where('agent_id', $agentId)
                    ->where('statut', 'en_cours')
                    ->firstOrFail();

        $nouveauStatut = $request->input('statut');
        $payload = ['statut' => $nouveauStatut];

        if ($nouveauStatut === 'publie') {
            $payload['publie_le']  = now();
            $payload['note_admin'] = null;
        }

        if ($nouveauStatut === 'rejete') {
            $payload['note_admin'] = $request->input('note_admin', 'Votre annonce a été rejetée après vérification.');
            $payload['publie_le']  = null;
        }

        $bien->update($payload);

        return response()->json([
            'success' => true,
            'message' => $nouveauStatut === 'publie'
                ? 'Bien publié avec succès.'
                : 'Bien rejeté. Le propriétaire sera notifié.',
            'data'    => new BienResource($bien->fresh(['medias', 'documents', 'proprietaire'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/biens/counts
    // Compteurs par onglet pour les badges de navigation
    // ─────────────────────────────────────────────────────────────────────────

    public function counts(Request $request): JsonResponse
    {
        $agentId = $request->user()->id;

        $nonAssigne = Bien::where('statut', 'en_attente')
                          ->whereNull('agent_id')
                          ->count();

        $enCours = Bien::where('statut', 'en_cours')
                       ->where('agent_id', $agentId)
                       ->count();

        $termine = Bien::whereIn('statut', ['publie', 'rejete', 'archive'])
                       ->where('agent_id', $agentId)
                       ->count();

        return response()->json([
            'success' => true,
            'data'    => compact('nonAssigne', 'enCours', 'termine'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/documents/{docId}
    // Téléchargement sécurisé d'un document privé (disk local)
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadDocument(Request $request, string $docId)
    {
        $user = $request->user();

        // Charger le document avec le bien pour vérifier les droits
        $document = DocumentBien::with('bien')->findOrFail($docId);

        // L'agent doit être assigné au bien (ou le bien doit être non assigné) - Sauf s'il est admin
        $bien = $document->bien;
        if ($user->role !== 'admin' && $bien->agent_id !== null && $bien->agent_id !== $user->id) {
            abort(403, 'Accès refusé à ce document.');
        }

        // Vérifier que le fichier existe sur le disk local
        if (! Storage::disk('local')->exists($document->chemin)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('local')->response(
            $document->chemin,
            $document->nom_original,
            ['Content-Type' => $document->mime_type]
        );
    }
}
