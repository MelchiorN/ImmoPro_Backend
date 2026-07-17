<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Mail\VisitePlanifieeNotification;
use App\Models\Bien;
use App\Models\User;
use App\Models\Visite;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AgentVisiteController extends Controller
{
    public function __construct(private readonly NotificationService $notifService) {}

    // ── POST /api/agent/biens/{id}/visites — Planifier une visite ────────────
    public function store(Request $request, string $bienId): JsonResponse
    {
        $agent = $request->user();

        $request->validate([
            'date_visite' => 'required|date|after_or_equal:' . now()->subMinutes(5)->toDateTimeString(),
            'notes'       => 'nullable|string|max:500',
        ], [
            'date_visite.required'       => 'La date et l\'heure de visite sont obligatoires.',
            'date_visite.date'           => 'Le format de la date est invalide.',
            'date_visite.after_or_equal' => 'La date de visite doit être dans le futur.',
            'notes.max'                  => 'Les notes ne doivent pas dépasser 500 caractères.',
        ]);

        $bien = Bien::where('id', $bienId)
            ->where('agent_id', $agent->id)
            ->whereIn('statut', ['en_cours', 'en_attente'])
            ->with(['proprietaire'])
            ->firstOrFail();

        // Pas de doublon de visite planifiée
        $existing = Visite::where('bien_id', $bienId)
            ->whereIn('statut', ['planifiee', 'confirmee'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Une visite est déjà planifiée pour ce bien.',
                'visite'  => $this->formatVisite($existing),
            ], 409);
        }

        $visite = Visite::create([
            'bien_id'     => $bienId,
            'agent_id'    => $agent->id,
            'date_visite' => $request->input('date_visite'),
            'notes'       => $request->input('notes'),
            'statut'      => 'planifiee',
        ]);

        $visite->load(['bien', 'agent']);

        $nomAgent   = trim("{$agent->first_name} {$agent->last_name}");
        $dateVisite = $visite->date_visite?->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm');
        $notes      = $visite->notes;

        // ── Notifier le propriétaire ──────────────────────────────────────────
        if ($bien->proprietaire) {
            $proprio = $bien->proprietaire;

            // Message in-app avec notes si présentes
            $messageProprio = "L'agent {$nomAgent} a planifié une visite de votre bien « {$bien->titre} » le {$dateVisite}.";
            if ($notes) {
                $messageProprio .= " Note de l'agent : {$notes}";
            }

            // Email HTML
            $emailRows = [
                ['icon' => '🏠', 'label' => 'Bien concerné',        'value' => $bien->titre . ($bien->adresse ? " — {$bien->adresse}" : '')],
                ['icon' => '📅', 'label' => 'Date et heure',        'value' => $dateVisite],
                ['icon' => '👤', 'label' => 'Agent assigné',         'value' => $nomAgent],
            ];
            $emailHtml = EmailTemplateService::generic(
                titre:   '📅 Visite planifiée pour votre bien',
                intro:   "Un agent ImmoPro a planifié une visite sur votre bien. Voici les informations :",
                rows:    $emailRows,
                noteBox: $notes ?: null,
                outro:   "Cette visite fait partie du processus de vérification de votre annonce. Votre agent vous contactera si des informations complémentaires sont nécessaires.",
            );

            $this->notifService->notify(
                user:         $proprio,
                type:         'visite_planifiee',
                titre:        'Visite planifiée',
                message:      $messageProprio,
                data:         [
                    'visite_id'   => $visite->id,
                    'bien_id'     => $bien->id,
                    'bien_titre'  => $bien->titre,
                    'agent_id'    => $agent->id,
                    'agent_nom'   => $nomAgent,
                    'date_visite' => $visite->date_visite?->toIso8601String(),
                    'notes'       => $notes ?? '',
                ],
                emailSubject: "📅 Visite planifiée pour votre bien — ImmoPro",
                emailBody:    $emailHtml,
            );
        }

        // ── Notifier tous les admins ──────────────────────────────────────────
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $messageAdmin = "L'agent {$nomAgent} a planifié une visite pour le bien « {$bien->titre} » le {$dateVisite}.";
            if ($notes) {
                $messageAdmin .= " Note : {$notes}";
            }

            $adminEmailHtml = EmailTemplateService::generic(
                titre: '📅 Nouvelle visite planifiée',
                intro: "Un agent vient de planifier une visite. Voici le résumé :",
                rows:  [
                    ['icon' => '🏠', 'label' => 'Bien',           'value' => $bien->titre],
                    ['icon' => '👤', 'label' => 'Agent',           'value' => $nomAgent],
                    ['icon' => '📅', 'label' => 'Date de visite',  'value' => $dateVisite],
                ],
                noteBox: $notes ?: null,
            );

            $this->notifService->notify(
                user:         $admin,
                type:         'visite_planifiee_admin',
                titre:        'Nouvelle visite planifiée',
                message:      $messageAdmin,
                data:         [
                    'visite_id'   => $visite->id,
                    'bien_id'     => $bien->id,
                    'bien_titre'  => $bien->titre,
                    'agent_id'    => $agent->id,
                    'agent_nom'   => $nomAgent,
                    'date_visite' => $visite->date_visite?->toIso8601String(),
                    'notes'       => $notes ?? '',
                ],
                emailSubject: "ImmoPro — Visite planifiée : {$bien->titre}",
                emailBody:    $adminEmailHtml,
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Visite planifiée avec succès. Le propriétaire a été notifié.',
            'visite'  => $this->formatVisite($visite),
        ], 201);
    }

    // ── GET /api/agent/biens/{id}/visites — Lister les visites d'un bien ────
    public function index(Request $request, string $bienId): JsonResponse
    {
        $visites = Visite::where('bien_id', $bienId)
            ->where('agent_id', $request->user()->id)
            ->orderBy('date_visite')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $visites->map(fn ($v) => $this->formatVisite($v)),
        ]);
    }

    // ── GET /api/agent/visites — Toutes les visites de l'agent (calendrier) ──
    public function allVisites(Request $request): JsonResponse
    {
        $visites = Visite::with(['bien'])
            ->where('agent_id', $request->user()->id)
            ->orderBy('date_visite')
            ->get()
            ->map(function (Visite $v) {
                return array_merge($this->formatVisite($v), [
                    'bien_titre'   => $v->bien?->titre,
                    'bien_adresse' => $v->bien?->adresse,
                    'bien_id'      => $v->bien_id,
                ]);
            });

        return response()->json([
            'success' => true,
            'data'    => $visites,
        ]);
    }

    // ── PATCH /api/agent/visites/{id} — Confirmer / Annuler / Soumettre rapport
    public function update(Request $request, string $visiteId): JsonResponse
    {
        $agent = $request->user();

        $visite = Visite::where('id', $visiteId)
            ->where('agent_id', $agent->id)
            ->with(['bien.proprietaire'])
            ->firstOrFail();

        $request->validate([
            'statut'           => 'required|in:confirmee,annulee,rapport_soumis',
            'rapport'          => 'required_if:statut,rapport_soumis|nullable|string',
            'visite_effectuee' => 'required_if:statut,rapport_soumis|nullable|boolean',
        ]);

        $visite->update($request->only('statut', 'rapport', 'visite_effectuee'));
        $visite = $visite->fresh(['bien.proprietaire']);

        $bien       = $visite->bien;
        $nomAgent   = trim("{$agent->first_name} {$agent->last_name}");
        $dateVisite = $visite->date_visite?->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm');

        // Déterminer les messages selon le nouveau statut
        [$titreProprio, $msgProprio, $titreAgent, $msgAgent] = match ($request->input('statut')) {
            'confirmee' => [
                'Visite confirmée',
                "L'agent {$nomAgent} a confirmé la visite de « {$bien?->titre} » prévue le {$dateVisite}.",
                'Visite confirmée',
                "Vous avez confirmé la visite du bien « {$bien?->titre} » prévue le {$dateVisite}.",
            ],
            'annulee' => [
                'Visite annulée',
                "L'agent {$nomAgent} a annulé la visite de « {$bien?->titre} » prévue le {$dateVisite}.",
                'Visite annulée',
                "Vous avez annulé la visite du bien « {$bien?->titre} ».",
            ],
            'rapport_soumis' => [
                'Rapport de visite soumis',
                "L'agent {$nomAgent} a soumis un rapport de visite pour votre bien « {$bien?->titre} ».",
                'Rapport soumis',
                "Votre rapport de visite pour « {$bien?->titre} » a bien été soumis et est en cours d'examen.",
            ],
            default => ['Mise à jour de visite', '', 'Mise à jour de visite', ''],
        };

        $dataCommon = [
            'visite_id'   => $visite->id,
            'bien_id'     => $bien?->id,
            'bien_titre'  => $bien?->titre,
            'agent_id'    => $agent->id,
            'agent_nom'   => $nomAgent,
            'date_visite' => $visite->date_visite?->toIso8601String(),
            'statut'      => $request->input('statut'),
        ];

        // ── Notifier le propriétaire ──────────────────────────────────────────
        if ($bien?->proprietaire && $msgProprio) {
            $emailHtml = EmailTemplateService::generic(
                titre: $titreProprio,
                intro: $msgProprio,
                rows:  [
                    ['icon' => '🏠', 'label' => 'Bien',   'value' => $bien->titre],
                    ['icon' => '📅', 'label' => 'Date',   'value' => $dateVisite],
                    ['icon' => '👤', 'label' => 'Agent',  'value' => $nomAgent],
                ],
            );

            $this->notifService->notify(
                user:         $bien->proprietaire,
                type:         'visite_' . $request->input('statut'),
                titre:        $titreProprio,
                message:      $msgProprio,
                data:         $dataCommon,
                emailSubject: "ImmoPro — {$titreProprio} : {$bien->titre}",
                emailBody:    $emailHtml,
            );
        }

        // ── Notifier l'agent (confirmation de son action) ─────────────────────
        if ($msgAgent) {
            $agentEmailHtml = EmailTemplateService::generic(
                titre: $titreAgent,
                intro: $msgAgent,
                rows:  [
                    ['icon' => '🏠', 'label' => 'Bien',  'value' => $bien?->titre ?? ''],
                    ['icon' => '📅', 'label' => 'Date',  'value' => $dateVisite],
                ],
            );

            $this->notifService->notify(
                user:         $agent,
                type:         'visite_' . $request->input('statut') . '_agent',
                titre:        $titreAgent,
                message:      $msgAgent,
                data:         $dataCommon,
                emailSubject: "ImmoPro — {$titreAgent}",
                emailBody:    $agentEmailHtml,
            );
        }

        // ── Notifier les admins ────────────────────────────────────────────────
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $msgAdmin = "L'agent {$nomAgent} a mis à jour la visite du bien « {$bien?->titre} » → statut : {$request->input('statut')}.";

            $adminEmailHtml = EmailTemplateService::generic(
                titre: 'Mise à jour de visite',
                intro: $msgAdmin,
                rows:  [
                    ['icon' => '🏠', 'label' => 'Bien',    'value' => $bien?->titre ?? ''],
                    ['icon' => '👤', 'label' => 'Agent',   'value' => $nomAgent],
                    ['icon' => '📅', 'label' => 'Date',    'value' => $dateVisite],
                    ['icon' => '📋', 'label' => 'Nouveau statut', 'value' => $request->input('statut')],
                ],
            );

            $this->notifService->notify(
                user:         $admin,
                type:         'visite_update_admin',
                titre:        'Mise à jour de visite',
                message:      $msgAdmin,
                data:         $dataCommon,
                emailSubject: "ImmoPro — Visite mise à jour : {$bien?->titre}",
                emailBody:    $adminEmailHtml,
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Visite mise à jour.',
            'visite'  => $this->formatVisite($visite),
        ]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function formatVisite(Visite $v): array
    {
        return [
            'id'               => $v->id,
            'date_visite'      => $v->date_visite?->toIso8601String(),
            'notes'            => $v->notes,
            'statut'           => $v->statut,
            'rapport'          => $v->rapport,
            'visite_effectuee' => $v->visite_effectuee,
            'created_at'       => $v->created_at->toIso8601String(),
        ];
    }
}
