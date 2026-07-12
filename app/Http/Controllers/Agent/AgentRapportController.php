<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Notification;
use App\Models\Rapport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AgentRapportController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/rapports
    // Liste des rapports de l'agent connecté
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->id;

        $rapports = Rapport::with(['bien.proprietaire', 'bien.medias'])
            ->where('agent_id', $agentId)
            ->latest()
            ->get()
            ->map(fn ($r) => $this->formatRapport($r));

        return response()->json([
            'success' => true,
            'data'    => $rapports,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/rapports/{id}
    // Détail d'un rapport
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->id;

        $rapport = Rapport::with(['bien.proprietaire', 'bien.medias', 'bien.documents', 'agent'])
            ->where('agent_id', $agentId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatRapport($rapport),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/biens/{bienId}/rapport
    // Rapport associé à un bien (pour la colonne rapport dans le tableau)
    // ─────────────────────────────────────────────────────────────────────────

    public function byBien(Request $request, string $bienId): JsonResponse
    {
        $agentId = $request->user()->id;

        $rapport = Rapport::with(['bien.proprietaire', 'bien.medias'])
            ->where('agent_id', $agentId)
            ->where('bien_id', $bienId)
            ->first();

        if (! $rapport) {
            return response()->json([
                'success' => true,
                'data'    => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatRapport($rapport),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/agent/rapports
    // Créer ou récupérer un brouillon
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bien_id'     => 'required|uuid|exists:biens,id',
            'titre'       => 'nullable|string|max:255',
            'contenu'     => 'nullable|string',
            'checklist'   => 'nullable|array',
            'note_finale' => 'nullable|string',
        ]);

        $agentId = $request->user()->id;

        // L'agent doit être assigné au bien
        $bien = Bien::where('agent_id', $agentId)
            ->whereIn('statut', ['en_cours', 'en_attente'])
            ->findOrFail($data['bien_id']);

        // Un seul rapport par agent/bien
        $rapport = Rapport::firstOrCreate(
            ['bien_id' => $bien->id, 'agent_id' => $agentId],
            [
                'titre'       => $data['titre'] ?? "Rapport — {$bien->titre}",
                'contenu'     => $data['contenu'] ?? '',
                'checklist'   => $data['checklist'] ?? [],
                'note_finale' => $data['note_finale'] ?? null,
                'statut'      => Rapport::STATUT_BROUILLON,
            ]
        );

        $rapport->load(['bien.proprietaire', 'bien.medias']);

        return response()->json([
            'success' => true,
            'data'    => $this->formatRapport($rapport),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/agent/rapports/{id}
    // Sauvegarder le brouillon (contenu, checklist, note)
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->id;

        // L'agent peut modifier son rapport tant qu'il n'est pas validé
        $rapport = Rapport::where('agent_id', $agentId)
            ->where('statut', '!=', Rapport::STATUT_VALIDE)
            ->findOrFail($id);

        $data = $request->validate([
            'titre'       => 'nullable|string|max:255',
            'contenu'     => 'nullable|string',
            'checklist'   => 'nullable|array',
            'note_finale' => 'nullable|string',
        ]);

        $rapport->update($data);
        $rapport->load(['bien.proprietaire', 'bien.medias']);

        return response()->json([
            'success' => true,
            'message' => 'Rapport sauvegardé.',
            'data'    => $this->formatRapport($rapport),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/agent/rapports/{id}/soumettre
    // Soumettre le rapport à l'admin pour décision
    // ─────────────────────────────────────────────────────────────────────────

    public function soumettre(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->id;
        $agent   = $request->user();

        $rapport = Rapport::with(['bien'])
            ->where('agent_id', $agentId)
            ->whereIn('statut', [Rapport::STATUT_BROUILLON, Rapport::STATUT_REJETE, Rapport::STATUT_SOUMIS])
            ->findOrFail($id);

        // Valider le contenu minimal
        $request->validate([
            'contenu'     => 'sometimes|string',
            'note_finale' => 'sometimes|string',
            'checklist'   => 'sometimes|array',
        ]);

        // Mettre à jour le contenu si fourni
        $updateData = array_filter([
            'contenu'     => $request->input('contenu'),
            'note_finale' => $request->input('note_finale'),
            'checklist'   => $request->input('checklist'),
        ], fn ($v) => $v !== null);

        $rapport->update(array_merge($updateData, [
            'statut'    => Rapport::STATUT_SOUMIS,
            'soumis_le' => now(),
            'note_rejet' => null, // reset rejet précédent
        ]));

        $bien = $rapport->bien;
        $nomAgent = trim("{$agent->first_name} {$agent->last_name}");

        // ── Notifications in-app pour tous les admins ─────────────────────────
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type'    => 'rapport_soumis',
                'titre'   => 'Rapport à examiner',
                'message' => "L'agent {$nomAgent} a soumis un rapport pour « {$bien->titre} ».",
                'canal'   => 'push',
                'lu'      => false,
                'data'    => [
                    'rapport_id' => $rapport->id,
                    'bien_id'    => $bien->id,
                    'bien_titre' => $bien->titre,
                    'agent_id'   => $agentId,
                    'agent_nom'  => $nomAgent,
                ],
            ]);

            // ── Email admin ───────────────────────────────────────────────────
            try {
                Mail::raw(
                    "Bonjour,\n\nL'agent {$nomAgent} a soumis un rapport d'inspection pour le bien :\n"
                    . "« {$bien->titre} » ({$bien->adresse})\n\n"
                    . "Connectez-vous à l'administration pour consulter ce rapport et décider de la publication.\n\n"
                    . "— ImmoPro",
                    fn ($msg) => $msg
                        ->to($admin->email)
                        ->subject("ImmoPro — Rapport à examiner : {$bien->titre}")
                );
            } catch (\Exception $e) {
                // Ne pas bloquer si l'email échoue
                \Log::warning("Email admin notification failed: " . $e->getMessage());
            }
        }

        $rapport->load(['bien.proprietaire', 'bien.medias']);

        return response()->json([
            'success' => true,
            'message' => 'Rapport soumis à l\'administration. Vous serez notifié de la décision.',
            'data'    => $this->formatRapport($rapport),
        ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    public function formatRapport(Rapport $r): array
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
            'note_rejet'  => $r->note_rejet,
            'soumis_le'   => $r->soumis_le?->toIso8601String(),
            'created_at'  => $r->created_at?->toIso8601String(),
            'updated_at'  => $r->updated_at?->toIso8601String(),
            'bien'        => $bien ? [
                'id'      => $bien->id,
                'titre'   => $bien->titre,
                'adresse' => $bien->adresse,
                'statut'  => $bien->statut,
                'photo'   => $photo?->url ?? $photo?->url_publique,
            ] : null,
            'agent' => isset($r->agent) ? [
                'id'         => $r->agent->id,
                'first_name' => $r->agent->first_name,
                'last_name'  => $r->agent->last_name,
                'email'      => $r->agent->email,
            ] : null,
            'client' => $bien?->proprietaire ? [
                'id'         => $bien->proprietaire->id,
                'first_name' => $bien->proprietaire->first_name,
                'last_name'  => $bien->proprietaire->last_name,
                'email'      => $bien->proprietaire->email,
            ] : null,
        ];
    }
}
