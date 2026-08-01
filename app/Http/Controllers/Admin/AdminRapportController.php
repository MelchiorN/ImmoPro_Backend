<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BienResource;
use App\Models\Bien;
use App\Models\Notification;
use App\Models\Rapport;
use App\Models\User;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminRapportController extends Controller
{
    public function __construct(private readonly NotificationService $notifService) {}

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/rapports
    // Liste tous les rapports soumis (+ filtres statut, search)
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Rapport::with(['bien.proprietaire', 'bien.medias', 'agent'])
            ->when(
                $request->query('statut'),
                fn ($q, $s) => $q->where('statut', $s),
                fn ($q)     => $q->whereIn('statut', ['soumis', 'valide', 'rejete'])
            )
            ->when(
                $request->query('search'),
                fn ($q, $s) => $q->whereHas('bien', fn ($bq) =>
                    $bq->where('titre', 'like', "%{$s}%")
                       ->orWhere('adresse', 'like', "%{$s}%")
                )
            )
            ->latest('soumis_le');

        $rapports = $query->paginate($request->query('per_page', 20));

        $formatAgent = fn (Rapport $r): array => [
            'id'          => $r->id,
            'titre'       => $r->titre,
            'statut'      => $r->statut,
            'note_finale' => $r->note_finale,
            'note_rejet'  => $r->note_rejet ?? $r->note_finale,
            'soumis_le'   => $r->soumis_le?->toIso8601String(),
            'created_at'  => $r->created_at?->toIso8601String(),
            'bien'        => $r->bien ? [
                'id'      => $r->bien->id,
                'titre'   => $r->bien->titre,
                'adresse' => $r->bien->adresse,
                'statut'  => $r->bien->statut,
                'photo'   => ($r->bien->medias?->firstWhere('est_principale', true)
                              ?? $r->bien->medias?->first())?->url,
            ] : null,
            'agent'  => $r->agent ? [
                'id'         => $r->agent->id,
                'first_name' => $r->agent->first_name,
                'last_name'  => $r->agent->last_name,
            ] : null,
            'proprietaire' => $r->bien?->proprietaire ? [
                'first_name' => $r->bien->proprietaire->first_name,
                'last_name'  => $r->bien->proprietaire->last_name,
                'email'      => $r->bien->proprietaire->email,
            ] : null,
        ];

        return response()->json([
            'success' => true,
            'data'    => $rapports->getCollection()->map($formatAgent)->values(),
            'meta'    => [
                'total'        => $rapports->total(),
                'per_page'     => $rapports->perPage(),
                'current_page' => $rapports->currentPage(),
                'last_page'    => $rapports->lastPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/rapports/{id}
    // Détail complet d'un rapport soumis
    // ─────────────────────────────────────────────────────────────────────────

    public function show(string $id): JsonResponse
    {
        $rapport = Rapport::with(['bien.proprietaire', 'bien.medias', 'bien.documents', 'agent'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatFull($rapport),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/biens/{id}/rapport
    // Lecture seule du rapport d'un bien — l'admin ne décide plus.
    // ─────────────────────────────────────────────────────────────────────────

    public function showByBien(string $bienId): JsonResponse
    {
        $rapport = Rapport::with(['bien.proprietaire', 'bien.medias', 'bien.documents', 'agent'])
            ->where('bien_id', $bienId)
            ->first();

        if (! $rapport) {
            return response()->json(['success' => true, 'data' => null]);
        }

        return response()->json(['success' => true, 'data' => $this->formatFull($rapport)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/admin/rapports/{id}/decision  — DÉSACTIVÉ
    // La décision appartient désormais à l'agent (POST /agent/biens/{id}/rapport/decision).
    // ─────────────────────────────────────────────────────────────────────────

    public function decision(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'La décision (approuver/rejeter) est maintenant prise par l\'agent, pas par l\'admin.',
        ], 403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/rapports/counts
    // ─────────────────────────────────────────────────────────────────────────

    public function counts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'soumis'  => Rapport::where('statut', 'soumis')->count(),
                'valide'  => Rapport::where('statut', 'valide')->count(),
                'rejete'  => Rapport::where('statut', 'rejete')->count(),
            ],
        ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function formatFull(Rapport $r): array
    {
        $bien  = $r->bien;
        $photo = $bien?->medias?->firstWhere('est_principale', true)
            ?? $bien?->medias?->first();

        return [
            'id'          => $r->id,
            'titre'       => $r->titre,
            'contenu'     => $r->contenu,
            'statut'      => $r->statut,
            'checklist'   => $r->checklist ?? [],
            'note_finale' => $r->note_finale,
            'note_rejet'  => $r->note_rejet ?? $r->note_finale,
            'soumis_le'   => $r->soumis_le?->toIso8601String(),
            'created_at'  => $r->created_at?->toIso8601String(),
            'bien'        => $bien ? [
                'id'               => $bien->id,
                'titre'            => $bien->titre,
                'adresse'          => $bien->adresse,
                'latitude'         => $bien->latitude ? (float) $bien->latitude : null,
                'longitude'        => $bien->longitude ? (float) $bien->longitude : null,
                'statut'           => $bien->statut,
                'type_bien'        => $bien->type_bien,
                'type_transaction' => $bien->type_transaction,
                'prix'             => (float) $bien->prix,
                'surface'          => $bien->surface ? (float) $bien->surface : null,
                'description'      => $bien->description,
                'photo'            => $photo?->url ?? $photo?->url_publique,
            ] : null,
            'agent' => $r->agent ? [
                'id'         => $r->agent->id,
                'first_name' => $r->agent->first_name,
                'last_name'  => $r->agent->last_name,
                'email'      => $r->agent->email,
            ] : null,
            'proprietaire' => $bien?->proprietaire ? [
                'first_name' => $bien->proprietaire->first_name,
                'last_name'  => $bien->proprietaire->last_name,
                'email'      => $bien->proprietaire->email,
            ] : null,
        ];
    }
}
