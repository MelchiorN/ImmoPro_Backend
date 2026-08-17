<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Rapport;
use App\Models\User;
use App\Notifications\DecisionBienAdminNotification;
use App\Notifications\DecisionBienNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentRapportController extends Controller
{
    public function __construct(private readonly NotificationService $notifService) {}

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

        // L'agent peut modifier son rapport uniquement s'il est en brouillon ou rejeté
        $rapport = Rapport::where('agent_id', $agentId)
            ->whereIn('statut', [Rapport::STATUT_BROUILLON, Rapport::STATUT_REJETE])
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
    // PUT /api/agent/biens/{bienId}/rapport/autosave
    // Auto-sauvegarde permanente — pas de "soumettre", l'admin lit en direct.
    // ─────────────────────────────────────────────────────────────────────────

    public function autosave(Request $request, string $bienId): JsonResponse
    {
        $agentId = $request->user()->id;

        $bien = Bien::where('id', $bienId)
            ->where('agent_id', $agentId)
            ->whereIn('statut', ['en_cours', 'en_attente'])
            ->firstOrFail();

        $data = $request->validate([
            'titre'       => 'nullable|string|max:255',
            'contenu'     => 'nullable|string',
            'checklist'   => 'nullable|array',
            'note_finale' => 'nullable|string',
        ]);

        $rapport = Rapport::updateOrCreate(
            ['bien_id' => $bien->id, 'agent_id' => $agentId],
            array_merge(['statut' => Rapport::STATUT_BROUILLON], array_filter($data, fn ($v) => $v !== null))
        );

        $bien->update(['last_activity_at' => now()]);

        $rapport->load(['bien.proprietaire', 'bien.medias']);

        return response()->json([
            'success' => true,
            'message' => 'Rapport sauvegardé.',
            'data'    => $this->formatRapport($rapport),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/agent/biens/{bienId}/rapport/decision
    // L'AGENT décide d'approuver ou rejeter — pas l'admin.
    // body : { "decision": "approuver"|"rejeter", "note": "..." }
    // ─────────────────────────────────────────────────────────────────────────

    public function decision(Request $request, string $bienId): JsonResponse
    {
        $agent = $request->user();

        $request->validate([
            'decision' => 'required|in:approuver,rejeter',
            'note'     => 'required_if:decision,rejeter|nullable|string|max:1000',
            'prix_visite' => 'nullable|numeric|min:0',
        ]);

        $bien = Bien::where('id', $bienId)
            ->where('agent_id', $agent->id)
            ->whereIn('statut', ['en_cours'])   // Seul en_cours autorise une décision
            ->with(['proprietaire'])
            ->firstOrFail();

        $rapport = Rapport::where('bien_id', $bienId)
            ->where('agent_id', $agent->id)
            ->firstOrFail();

        $nomAgent = trim("{$agent->first_name} {$agent->last_name}");

        if ($request->decision === 'approuver') {
            $rapport->update(['statut' => Rapport::STATUT_VALIDE]);

            // Calculer le prix de visite.
            // Priorité :
            //   1. prix_visite saisi explicitement par l'agent dans le formulaire
            //   2. Calcul automatique depuis la catégorie (pourcentage ou fixe)
            //   3. Fallback : 0 (bloqué plus bas si toujours à 0)
            $prixVisite = 0;

            // Priorité 1 — saisie explicite de l'agent (toujours prioritaire)
            if ($request->filled('prix_visite')) {
                $prixVisite = (float) $request->input('prix_visite');
            } else {
                // Priorité 2 — calcul depuis la catégorie
                $cat = $bien->getCategorie();
                if ($cat) {
                    if ($cat->visite_tarif_type === 'pourcentage' && $cat->visite_pourcentage > 0) {
                        $prixVisite = $cat->calculerPrixVisite((float) $bien->prix);
                    } elseif ($cat->visite_tarif_type === 'fixe_manuel' && (float) $cat->visite_tarif_fixe > 0) {
                        $prixVisite = (float) $cat->visite_tarif_fixe;
                    }
                    // Si tarif_fixe = 0 ou null sur la catégorie (type "autre"), $prixVisite reste 0
                }
            }

            $publicationAuto = (bool) ($bien->publication_auto ?? true);

            if ($publicationAuto) {
                // ── Publication automatique : le bien est directement publié ──
                $bien->update([
                    'statut'           => 'publie',
                    'publie_le'        => now(),
                    'note_admin'       => null,
                    'prix_visite'      => $prixVisite,
                    'last_activity_at' => now(),
                ]);

                if ($bien->proprietaire) {
                    $this->notifService->notify(
                        $bien->proprietaire,
                        'bien_valide_publie',
                        '🎉 Votre bien est validé et publié !',
                        "Bonne nouvelle ! Votre bien « {$bien->titre} » a été validé et est maintenant visible par tous sur la plateforme.",
                        ['bien_id' => (string) $bien->id],
                        'Votre bien est publié — ImmoPro',
                        \App\Services\EmailTemplateService::generic(
                            titre: '🎉 Votre bien est validé et publié !',
                            intro: "Votre bien « {$bien->titre} » a été validé par notre équipe et est maintenant disponible sur la plateforme ImmoPro.",
                            rows: [
                                ['icon' => '🏠', 'label' => 'Bien',    'value' => $bien->titre],
                                ['icon' => '📍', 'label' => 'Adresse', 'value' => $bien->adresse],
                                ['icon' => '✅', 'label' => 'Statut',  'value' => 'Publié et visible'],
                            ],
                            outro: 'Des locataires ou acheteurs peuvent maintenant vous contacter via ImmoPro.'
                        ),
                    );
                }
            } else {
                // ── Publication manuelle : le bien reste en statut "valide" ──
                // Le propriétaire recevra une notification pour publier lui-même
                $bien->update([
                    'statut'           => 'valide',
                    'note_admin'       => null,
                    'prix_visite'      => $prixVisite,
                    'last_activity_at' => now(),
                ]);

                if ($bien->proprietaire) {
                    $this->notifService->notify(
                        $bien->proprietaire,
                        'bien_valide_attente_publication',
                        '✅ Votre bien est validé — À vous de publier !',
                        "Votre bien « {$bien->titre} » a été validé. Rendez-vous dans vos annonces pour le publier quand vous le souhaitez.",
                        ['bien_id' => (string) $bien->id],
                        'Votre bien est validé — ImmoPro',
                        \App\Services\EmailTemplateService::generic(
                            titre: '✅ Votre bien est validé !',
                            intro: "Votre bien « {$bien->titre} » a été validé par notre équipe. Vous avez choisi de le publier vous-même.",
                            rows: [
                                ['icon' => '🏠', 'label' => 'Bien',    'value' => $bien->titre],
                                ['icon' => '📍', 'label' => 'Adresse', 'value' => $bien->adresse],
                                ['icon' => '✅', 'label' => 'Statut',  'value' => 'Validé — En attente de votre publication'],
                            ],
                            outro: 'Connectez-vous à votre espace ImmoPro et cliquez sur « Publier maintenant » pour rendre votre bien visible.'
                        ),
                    );
                }
            }

            // Notifier les admins
            foreach (User::where('role', 'admin')->get() as $admin) {
                $admin->notify(new DecisionBienAdminNotification($bien, 'approuve'));
            }

        } else {
            $note = $request->note ?? 'Non conforme.';
            $rapport->update(['statut' => Rapport::STATUT_REJETE, 'note_finale' => $note]);
            $bien->update(['statut' => 'rejete', 'note_admin' => $note, 'last_activity_at' => now()]);

            // Notifier le propriétaire
            if ($bien->proprietaire) {
                $bien->proprietaire->notify(new DecisionBienNotification($bien, 'rejete', $note));
            }

            // Notifier les admins
            foreach (User::where('role', 'admin')->get() as $admin) {
                $admin->notify(new DecisionBienAdminNotification($bien, 'rejete'));
            }
        }

        $rapport->load(['bien.proprietaire', 'bien.medias']);

        return response()->json([
            'success' => true,
            'message' => $request->decision === 'approuver' ? 'Bien approuvé.' : 'Bien rejeté.',
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
