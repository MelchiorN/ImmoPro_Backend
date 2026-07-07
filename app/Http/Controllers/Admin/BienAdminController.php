<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BienListResource;
use App\Http\Resources\BienResource;
use App\Models\Bien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BienAdminController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/biens
    // Liste tous les biens avec filtre statut (admin/agent)
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Bien::with(['medias', 'proprietaire'])
            ->when(
                $request->query('statut'),
                fn ($q, $s) => $q->where('statut', $s),
                fn ($q)     => $q->whereIn('statut', ['en_attente', 'publie', 'rejete'])
            )
            ->when(
                $request->query('type_bien'),
                fn ($q, $t) => $q->typeBien($t)
            )
            ->when(
                $request->query('search'),
                fn ($q, $s) => $q->where(function ($sq) use ($s) {
                    $sq->where('titre', 'like', "%{$s}%")
                       ->orWhere('adresse', 'like', "%{$s}%");
                })
            )
            ->latest();

        $biens = $query->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => BienListResource::collection($biens->items()),
            'meta'    => [
                'total'        => $biens->total(),
                'per_page'     => $biens->perPage(),
                'current_page' => $biens->currentPage(),
                'last_page'    => $biens->lastPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/biens/{id}
    // Détail complet (admin/agent)
    // ─────────────────────────────────────────────────────────────────────────

    public function show(string $id): JsonResponse
    {
        $bien = Bien::with(['medias', 'documents', 'proprietaire'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => new BienResource($bien),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/admin/biens/{id}/statut
    // Changer le statut : publier, rejeter, archiver
    // ─────────────────────────────────────────────────────────────────────────

    public function updateStatut(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'statut'     => 'required|in:publie,rejete,archive',
            'note_admin' => 'nullable|string|max:1000',
        ]);

        $bien = Bien::findOrFail($id);

        // Transitions autorisées
        $transitionsAutorisees = [
            'en_attente' => ['publie', 'rejete'],
            'rejete'     => ['publie'],
            'publie'     => ['archive', 'rejete'],
            'archive'    => ['publie'],
        ];

        $statutActuel = $bien->statut;
        $nouveauStatut = $request->input('statut');

        if (! in_array($nouveauStatut, $transitionsAutorisees[$statutActuel] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => "Transition de statut invalide : {$statutActuel} → {$nouveauStatut}.",
            ], 422);
        }

        $payload = ['statut' => $nouveauStatut];

        if ($nouveauStatut === 'publie') {
            $payload['publie_le']    = now();
            $payload['agent_id']     = $request->user()->id;
            $payload['note_admin']   = null;
        }

        if ($nouveauStatut === 'rejete') {
            $payload['note_admin'] = $request->input('note_admin', 'Votre annonce a été rejetée.');
            $payload['publie_le']  = null;
        }

        $bien->update($payload);

        return response()->json([
            'success' => true,
            'message' => "Statut mis à jour : {$nouveauStatut}.",
            'data'    => new BienResource($bien->fresh(['medias', 'documents', 'proprietaire'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/admin/biens/{id}/assigner
    // L'admin attribue manuellement un bien à un agent spécifique
    // ─────────────────────────────────────────────────────────────────────────

    public function assigner(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'agent_id' => 'required|uuid|exists:users,id',
        ]);

        $bien = Bien::where('statut', 'en_attente')->findOrFail($id);

        $bien->update(['agent_id' => $request->input('agent_id')]);

        return response()->json([
            'success' => true,
            'message' => 'Bien attribué à l\'agent.',
            'data'    => new BienResource($bien->fresh(['medias', 'documents', 'proprietaire'])),
        ]);
    }
}
