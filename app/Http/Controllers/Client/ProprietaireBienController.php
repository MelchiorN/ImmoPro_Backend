<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints dédiés au propriétaire (client connecté) pour consulter
 * l'état de ses annonces publiées, en cours, rejetées, etc.
 *
 * GET  /api/proprietaire/biens            → liste avec statuts + raison rejet
 * GET  /api/proprietaire/biens/{id}       → détail complet d'un bien
 * GET  /api/proprietaire/biens/stats      → compteurs par statut
 */
class ProprietaireBienController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/proprietaire/biens
    // Liste complète des biens du propriétaire connecté, tous statuts confondus.
    // Inclut les informations utiles pour le suivi : statut, note_admin, etc.
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Bien::with(['medias'])
            ->where('user_id', $userId)
            ->when(
                $request->query('statut'),
                fn ($q, $s) => $q->where('statut', $s)
            )
            ->latest();

        $biens = $query->paginate($request->query('per_page', 20));

        // Si le propriétaire n'a jamais soumis de bien, retourner un message clair
        if ($biens->total() === 0 && ! $request->has('statut')) {
            return response()->json([
                'success'  => true,
                'data'     => [],
                'meta'     => ['total' => 0, 'per_page' => 20, 'current_page' => 1, 'last_page' => 1],
                'message'  => "Vous n'avez pas encore soumis d'annonce. Commencez par publier votre premier bien !",
                'has_biens' => false,
            ]);
        }

        return response()->json([
            'success'   => true,
            'data'      => $biens->getCollection()->map(fn ($b) => $this->formatBien($b)),
            'meta'      => [
                'total'        => $biens->total(),
                'per_page'     => $biens->perPage(),
                'current_page' => $biens->currentPage(),
                'last_page'    => $biens->lastPage(),
            ],
            'has_biens' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/proprietaire/biens/stats
    // Compteurs par statut — utile pour les badges dans la nav mobile
    // ─────────────────────────────────────────────────────────────────────────

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $counts = Bien::where('user_id', $userId)
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        $total = array_sum($counts);

        return response()->json([
            'success' => true,
            'data'    => [
                'total'           => $total,
                'en_attente'      => $counts['en_attente']      ?? 0,
                'en_cours'        => $counts['en_cours']        ?? 0,
                'en_verification' => ($counts['en_attente'] ?? 0) + ($counts['en_cours'] ?? 0),
                'publie'          => $counts['publie']          ?? 0,
                'rejete'          => $counts['rejete']          ?? 0,
                'archive'         => $counts['archive']         ?? 0,
                'brouillon'       => $counts['brouillon']       ?? 0,
                'has_biens'       => $total > 0,
                'message'         => $total === 0
                    ? "Vous n'avez pas encore soumis d'annonce. Commencez par publier votre premier bien !"
                    : null,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/proprietaire/biens/{id}
    // Détail complet d'un bien (avec note_admin / raison_rejet visible)
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Request $request, string $id): JsonResponse
    {
        $bien = Bien::with(['medias', 'documents'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatBienDetail($bien),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Format allégé pour la liste — compatible avec ListingModel Flutter.
     */
    private function formatBien(Bien $b): array
    {
        $photo = $b->medias->firstWhere('est_principale', true)
            ?? $b->medias->first();

        return [
            'id'               => $b->id,
            'titre'            => $b->titre,
            'description'      => $b->description,
            'prix'             => (float) $b->prix,
            'adresse'          => $b->adresse,
            'type_bien'        => $b->type_bien,
            'type_transaction' => $b->type_transaction,
            'statut'           => $this->normalizeStatut($b->statut),
            'surface'          => $b->surface ? (float) $b->surface : null,
            'nb_pieces'        => $b->nb_pieces,
            'nb_salles_bain'   => $b->nb_salles_bain,
            'photo_principale' => $photo ? ($photo->url ?? null) : null,
            'medias'           => $b->medias->map(fn ($m) => [
                'id'             => $m->id,
                'type'           => $m->type === 'photo' ? 'image' : $m->type,
                'url'            => $m->url,
                'est_principale' => (bool) $m->est_principale,
                'ordre'          => $m->ordre,
            ])->values()->toArray(),
            // Informations de suivi
            'raison_rejet'     => $b->statut === 'rejete' ? $b->note_admin : null,
            'publie_le'        => $b->publie_le?->toIso8601String(),
            'created_at'       => $b->created_at->toIso8601String(),
        ];
    }

    /**
     * Format complet pour le détail.
     */
    private function formatBienDetail(Bien $b): array
    {
        return $this->formatBien($b);
    }

    /**
     * Normalise les statuts internes vers les valeurs attendues par le mobile.
     * 'en_cours' → 'en_verification' pour plus de clarté côté client.
     */
    private function normalizeStatut(string $statut): string
    {
        return match ($statut) {
            'en_cours'   => 'en_verification',
            'en_attente' => 'en_attente',
            default      => $statut,
        };
    }
}
