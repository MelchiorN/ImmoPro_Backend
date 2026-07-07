<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Resources\BienListResource;
use App\Http\Resources\BienResource;
use App\Models\Bien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentBienController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/biens
    // Pool de biens accessibles à l'agent connecté :
    //   - statut=en_attente  + agent_id IS NULL  → onglet "Non assignés"
    //   - statut=en_attente  + agent_id = moi    → onglet "En cours"
    //   - statut=publie|rejete + agent_id = moi  → onglet "Terminés"
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $agentId   = $request->user()->id;
        $onglet    = $request->query('onglet', 'non_assigne'); // non_assigne | en_cours | termine

        $query = Bien::with(['medias', 'proprietaire'])
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
                // Biens en attente que cet agent a pris en charge
                $query->where('statut', 'en_attente')
                      ->where('agent_id', $agentId);
                break;

            case 'termine':
                // Biens publiés ou rejetés par cet agent
                $query->whereIn('statut', ['publie', 'rejete', 'archive'])
                      ->where('agent_id', $agentId);
                break;

            default:
                // Fallback : non assignés
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
    // L'agent prend en charge un bien non assigné (verrou optimiste)
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

            $bien->update(['agent_id' => $agentId]);
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
    // POST /api/agent/biens/{id}/release
    // L'agent libère un bien qu'il a pris (remet dans le pool)
    // ─────────────────────────────────────────────────────────────────────────

    public function release(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->id;

        $bien = Bien::where('id', $id)
                    ->where('agent_id', $agentId)
                    ->where('statut', 'en_attente')
                    ->firstOrFail();

        $bien->update(['agent_id' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Bien remis dans le pool. Il est de nouveau disponible.',
        ]);
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

        // L'agent ne peut agir que sur les biens qu'il a pris en charge
        $bien = Bien::where('id', $id)
                    ->where('agent_id', $agentId)
                    ->where('statut', 'en_attente')
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

        $enCours = Bien::where('statut', 'en_attente')
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
}
