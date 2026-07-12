<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BienResource;
use App\Models\Bien;
use App\Models\Notification;
use App\Models\Rapport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminRapportController extends Controller
{
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
    // POST /api/admin/rapports/{id}/decision
    // L'admin décide : publier ou rejeter le bien après lecture du rapport
    // body : { "decision": "publier"|"rejeter", "note": "..." }
    // ─────────────────────────────────────────────────────────────────────────

    public function decision(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'decision' => 'required|in:publier,rejeter',
            'note'     => 'nullable|string|max:1000',
        ]);

        $rapport = Rapport::with(['bien', 'agent'])->findOrFail($id);

        if ($rapport->statut !== Rapport::STATUT_SOUMIS) {
            return response()->json([
                'success' => false,
                'message' => 'Ce rapport a déjà été traité.',
            ], 422);
        }

        $bien     = $rapport->bien;
        $decision = $request->input('decision');
        $note     = $request->input('note');
        $admin    = $request->user();
        $nomAdmin = trim("{$admin->first_name} {$admin->last_name}");

        if ($decision === 'publier') {
            // ── Publier ───────────────────────────────────────────────────────
            $rapport->update([
                'statut'      => Rapport::STATUT_VALIDE,
                'note_finale' => null,
            ]);

            $bien->update([
                'statut'     => 'publie',
                'publie_le'  => now(),
                'note_admin' => null,
            ]);

            $messageAgent = "Félicitations ! Votre rapport pour « {$bien->titre} » a été approuvé. Le bien est maintenant publié.";
            $typeNotif    = 'rapport_approuve';
            $titreNotif   = 'Rapport approuvé — bien publié';

        } else {
            // ── Rejeter ───────────────────────────────────────────────────────
            $rapport->update([
                'statut'      => Rapport::STATUT_REJETE,
                'note_finale' => $note ?? 'Le rapport nécessite des corrections.',
            ]);

            // Le bien repasse en en_cours pour correction
            $bien->update([
                'statut'     => 'en_cours',
                'note_admin' => $note,
            ]);

            $messageAgent = "Votre rapport pour « {$bien->titre} » a été rejeté. Motif : " . ($note ?? 'Aucun motif précisé') . ". Veuillez le corriger et le soumettre à nouveau.";
            $typeNotif    = 'rapport_rejete';
            $titreNotif   = 'Rapport rejeté — corrections requises';
        }

        // ── Notification in-app à l'agent ────────────────────────────────────
        Notification::create([
            'user_id' => $rapport->agent_id,
            'type'    => $typeNotif,
            'titre'   => $titreNotif,
            'message' => $messageAgent,
            'canal'   => 'push',
            'lu'      => false,
            'data'    => [
                'rapport_id' => $rapport->id,
                'bien_id'    => $bien->id,
                'bien_titre' => $bien->titre,
                'decision'   => $decision,
                'note'       => $note,
            ],
        ]);

        // ── Email à l'agent ───────────────────────────────────────────────────
        if ($rapport->agent) {
            try {
                Mail::raw(
                    "Bonjour {$rapport->agent->first_name},\n\n{$messageAgent}\n\n— ImmoPro Administration",
                    fn ($msg) => $msg
                        ->to($rapport->agent->email)
                        ->subject("ImmoPro — {$titreNotif} : {$bien->titre}")
                );
            } catch (\Exception $e) {
                \Log::warning("Email agent notification failed: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => $decision === 'publier'
                ? 'Bien publié avec succès.'
                : 'Rapport rejeté. L\'agent a été notifié.',
            'data'    => $this->formatFull($rapport->fresh(['bien', 'agent'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/rapports/counts
    // Compteurs pour le badge dans l'interface admin
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
