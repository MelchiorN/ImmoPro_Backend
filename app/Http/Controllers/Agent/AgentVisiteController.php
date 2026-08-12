<?php

namespace App\Http\Controllers\Agent;

use App\Events\VisiteStatutChanged;
use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\CreneauVisite;
use App\Models\User;
use App\Models\Visite;
use App\Notifications\ClientIndisponibleNotification;
use App\Services\CalendrierBienService;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentVisiteController extends Controller
{
    public function __construct(private readonly NotificationService $notifService) {}

    // ─────────────────────────────────────────────────────────────────────
    // SECTION 1 : CRÉNEAUX DE DISPONIBILITÉ (sans bien)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /api/agent/creneaux/disponibles
     * Liste tous les créneaux libres de l'agent (sans bien ou avec bien)
     */
    public function creneauxDisponibles(Request $request): JsonResponse
    {
        $agent    = $request->user();
        $futurOnly = $request->boolean('futur', true);

        $query = CreneauVisite::where('agent_id', $agent->id)
            ->where('statut', 'disponible')
            ->with('bien')
            ->orderBy('date_debut');

        if ($futurOnly) {
            $query->where('date_debut', '>', now());
        }

        $data = $query->get()->map(fn ($c) => $this->formatCreneau($c));

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * POST /api/agent/creneaux
     * Créer un ou plusieurs créneaux de disponibilité libres (sans bien obligatoire).
     */
    public function storeCreneauLibre(Request $request): JsonResponse
    {
        $agent = $request->user();

        $request->validate([
            'creneaux'                 => 'required|array|min:1|max:20',
            'creneaux.*.date_debut'    => 'required|date|after:now',
            'creneaux.*.duree_minutes' => 'nullable|integer|min:15|max:480',
        ]);

        $created = [];
        $dureeRef = null; // durée de référence pour propagation automatique

        foreach ($request->creneaux as $item) {
            $debut = Carbon::parse($item['date_debut']);

            // Propagation automatique : si durée non fournie, utiliser la durée de référence
            if (isset($item['duree_minutes']) && $item['duree_minutes']) {
                $dureeRef = (int) $item['duree_minutes'];
            }
            $duree = $dureeRef ?? 60;
            $fin   = $debut->copy()->addMinutes($duree);

            // Vérifier chevauchement dans le calendrier de l'agent
            $conflit = $this->detecterConflitAgent($agent->id, $debut, $duree);
            if ($conflit) {
                return response()->json([
                    'success' => false,
                    'message' => "Conflit détecté : le créneau du {$debut->format('d/m H:i')} chevauche une visite confirmée.",
                    'conflit' => $conflit,
                ], 409);
            }

            $creneau = CreneauVisite::create([
                'bien_id'    => null,
                'agent_id'   => $agent->id,
                'date_debut' => $debut,
                'date_fin'   => $fin,
                'statut'     => 'disponible',
            ]);
            $created[] = $this->formatCreneau($creneau);
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' créneau(x) créé(s).',
            'data'    => $created,
        ], 201);
    }

    /**
     * DELETE /api/agent/creneaux/{id}
     * Supprimer un créneau libre (uniquement si disponible).
     */
    public function deleteCreneauLibre(Request $request, string $id): JsonResponse
    {
        $creneau = CreneauVisite::where('id', $id)
            ->where('agent_id', $request->user()->id)
            ->where('statut', 'disponible')
            ->firstOrFail();

        $creneau->delete();

        return response()->json(['success' => true, 'message' => 'Créneau supprimé.']);
    }


    // ─────────────────────────────────────────────────────────────────────
    // SECTION 2 : CRÉNEAUX PAR BIEN (vérification)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /api/agent/biens/{bienId}/creneaux
     */
    public function getCreneaux(Request $request, string $bienId): JsonResponse
    {
        $agent = $request->user();

        Bien::where('id', $bienId)
            ->where(fn ($q) => $q->where('agent_id', $agent->id)->orWhereNull('agent_id'))
            ->firstOrFail();

        $creneaux = CreneauVisite::where('bien_id', $bienId)
            ->where('agent_id', $agent->id)
            ->orderBy('date_debut')
            ->get()
            ->map(fn ($c) => $this->formatCreneau($c));

        return response()->json(['success' => true, 'data' => $creneaux]);
    }

    /**
     * POST /api/agent/biens/{bienId}/creneaux
     * Propose des créneaux pour la visite de vérification d'un bien.
     * L'agent peut saisir manuellement ou sélectionner depuis ses créneaux libres.
     */
    public function proposeCreneaux(Request $request, string $bienId): JsonResponse
    {
        $agent = $request->user();

        $request->validate([
            'creneaux'                   => 'required|array|min:1|max:10',
            'creneaux.*.date_debut'      => 'required|date|after:now',
            'creneaux.*.duree_minutes'   => 'nullable|integer|min:15|max:480',
            // optionnel : ID d'un créneau libre existant à assigner
            'creneaux.*.creneau_libre_id'=> 'nullable|uuid|exists:creneaux_visite,id',
        ]);

        $bien = Bien::where('id', $bienId)
            ->where('agent_id', $agent->id)
            ->with('proprietaire')
            ->firstOrFail();

        $service  = app(CalendrierBienService::class);
        $dureeDef = (int) (config('app.visite_duree_defaut', 45));

        if (! $service->phaseAutorisee($bien, 'verification')) {
            return response()->json([
                'success' => false,
                'message' => 'Ce bien n\'est pas en phase de vérification.',
            ], 403);
        }

        $created  = [];
        $dureeRef = null;

        foreach ($request->creneaux as $item) {
            $debut = Carbon::parse($item['date_debut']);

            if (isset($item['duree_minutes']) && $item['duree_minutes']) {
                $dureeRef = (int) $item['duree_minutes'];
            }
            $duree = $dureeRef ?? $dureeDef;
            $fin   = $debut->copy()->addMinutes($duree);

            // Vérifier conflit agent (toutes visites confirmées de l'agent)
            $conflit = $this->detecterConflitAgent($agent->id, $debut, $duree);
            if ($conflit) {
                return response()->json([
                    'success' => false,
                    'message' => "Conflit : le créneau du {$debut->format('d/m H:i')} chevauche une visite déjà confirmée.",
                    'conflit' => $conflit,
                ], 409);
            }

            // Si un créneau libre est fourni, le recycler
            if (!empty($item['creneau_libre_id'])) {
                $libre = CreneauVisite::where('id', $item['creneau_libre_id'])
                    ->where('agent_id', $agent->id)
                    ->where('statut', 'disponible')
                    ->first();
                if ($libre) {
                    $libre->update(['bien_id' => $bienId]);
                    $created[] = $libre;
                    continue;
                }
            }

            if (! $service->creneauDisponible($bienId, $debut, $duree)) {
                return response()->json([
                    'success' => false,
                    'message' => "Le créneau du {$debut->format('d/m H:i')} est déjà occupé pour ce bien.",
                ], 409);
            }

            $created[] = CreneauVisite::create([
                'bien_id'    => $bienId,
                'agent_id'   => $agent->id,
                'date_debut' => $debut,
                'date_fin'   => $fin,
                'statut'     => 'disponible',
            ]);
        }

        // --- CORRECTION BUG : Il faut s'assurer qu'une Visite de vérification existe ---
        $visite = Visite::firstOrCreate(
            ['bien_id' => $bienId, 'type_visite' => Visite::TYPE_VERIFICATION],
            ['agent_id' => $agent->id, 'statut' => Visite::STATUT_EN_ATTENTE_CLIENT]
        );
        
        // Si elle existait déjà mais était dans un autre statut
        if ($visite->statut !== Visite::STATUT_EN_ATTENTE_CLIENT) {
            $visite->update([
                'statut' => Visite::STATUT_EN_ATTENTE_CLIENT,
                'agent_id' => $agent->id,
            ]);
        }
        // -------------------------------------------------------------------------------

        $nb = count($created);
        $nomAgent = trim("{$agent->first_name} {$agent->last_name}");

        if ($bien->proprietaire) {
            $html = EmailTemplateService::generic(
                titre: '📅 Créneaux de visite disponibles',
                intro: "{$nomAgent} vous propose {$nb} créneau(x) pour la vérification de votre bien « {$bien->titre} ».",
                rows:  [['icon' => '🏠', 'label' => 'Bien', 'value' => $bien->titre]],
                outro: 'Connectez-vous à votre espace pour choisir le créneau qui vous convient.'
            );
            $this->notifService->notify(
                $bien->proprietaire, 'creneaux_proposes',
                'Créneaux de visite disponibles',
                "{$nomAgent} vous propose {$nb} créneau(x) pour « {$bien->titre} ».",
                ['bien_id' => $bien->id, 'nb_creneaux' => $nb],
                "ImmoPro — Créneaux de visite pour « {$bien->titre} »", $html
            );
        }

        return response()->json([
            'success' => true,
            'message' => "{$nb} créneau(x) proposé(s) au propriétaire.",
            'data'    => collect($created)->map(fn ($c) => $this->formatCreneau($c)),
        ], 201);
    }

    /**
     * DELETE /api/agent/biens/{bienId}/creneaux/{creneauId}
     */
    public function deleteCreneaux(Request $request, string $bienId, string $creneauId): JsonResponse
    {
        $creneau = CreneauVisite::where('id', $creneauId)
            ->where('bien_id', $bienId)
            ->where('agent_id', $request->user()->id)
            ->where('statut', 'disponible')
            ->firstOrFail();

        $creneau->delete();
        return response()->json(['success' => true, 'message' => 'Créneau supprimé.']);
    }


    // ─────────────────────────────────────────────────────────────────────
    // SECTION 3 : VISITES DE VÉRIFICATION
    // ─────────────────────────────────────────────────────────────────────

    /** GET /api/agent/biens/{id}/visites */
    public function index(Request $request, string $bienId): JsonResponse
    {
        $visites = Visite::where('bien_id', $bienId)
            ->where('agent_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $visites->map(fn ($v) => $this->formatVisite($v)),
        ]);
    }

    /** GET /api/agent/visites — Toutes les visites de l'agent (calendrier) */
    public function allVisites(Request $request): JsonResponse
    {
        $visites = Visite::with(['bien'])
            ->where('agent_id', $request->user()->id)
            ->orderBy('date_visite')
            ->get()
            ->map(fn (Visite $v) => array_merge($this->formatVisite($v), [
                'bien_titre'   => $v->bien?->titre,
                'bien_adresse' => $v->bien?->adresse,
                'bien_id'      => $v->bien_id,
            ]));

        return response()->json(['success' => true, 'data' => $visites]);
    }

    /** GET /api/agent/creneaux — Tous les créneaux de l'agent (calendrier) */
    public function allCreneaux(Request $request): JsonResponse
    {
        $creneaux = CreneauVisite::with(['bien'])
            ->where('agent_id', $request->user()->id)
            ->orderBy('date_debut')
            ->get()
            ->map(fn ($c) => $this->formatCreneau($c));

        return response()->json(['success' => true, 'data' => $creneaux]);
    }

    /**
     * PATCH /api/agent/visites/{id}
     * Confirmer / Annuler / Soumettre rapport / Confirmer visite effectuée
     */
    public function update(Request $request, string $visiteId): JsonResponse
    {
        $agent = $request->user();

        $visite = Visite::where('id', $visiteId)
            ->where('agent_id', $agent->id)
            ->with(['bien.proprietaire'])
            ->firstOrFail();

        $request->validate([
            'statut'           => 'required|in:confirmee,annulee,rapport_soumis,visite_effectuee_confirmee',
            'rapport'          => 'required_if:statut,rapport_soumis|nullable|string',
            'visite_effectuee' => 'required_if:statut,rapport_soumis,visite_effectuee_confirmee|nullable|boolean',
        ]);

        // Statut spécial : l'agent confirme que la visite a physiquement eu lieu
        if ($request->input('statut') === 'visite_effectuee_confirmee') {
            $visite->update(['visite_effectuee' => true]);

            $bien     = $visite->bien;
            $nomAgent = trim("{$agent->first_name} {$agent->last_name}");

            if ($bien?->proprietaire) {
                $this->notifService->notify(
                    $bien->proprietaire, 'visite_effectuee',
                    'Visite effectuée',
                    "L'agent {$nomAgent} confirme que la visite de « {$bien->titre} » a eu lieu.",
                    ['visite_id' => $visite->id, 'bien_id' => $bien->id],
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Visite marquée comme effectuée.',
                'visite'  => $this->formatVisite($visite->fresh()),
            ]);
        }

        $visite->update($request->only('statut', 'rapport', 'visite_effectuee'));
        $visite = $visite->fresh(['bien.proprietaire']);

        $bien       = $visite->bien;
        $nomAgent   = trim("{$agent->first_name} {$agent->last_name}");
        $dateVisite = $visite->date_visite?->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm');

        [$titreProprio, $msgProprio, $titreAgent, $msgAgent] = match ($request->input('statut')) {
            'confirmee' => [
                'Visite confirmée',
                "L'agent {$nomAgent} a confirmé la visite de « {$bien?->titre} » prévue le {$dateVisite}.",
                'Visite confirmée',
                "Vous avez confirmé la visite du bien « {$bien?->titre} » prévue le {$dateVisite}.",
            ],
            'annulee' => [
                'Visite annulée',
                "L'agent {$nomAgent} a annulé la visite de « {$bien?->titre} ».",
                'Visite annulée',
                "Vous avez annulé la visite du bien « {$bien?->titre} ».",
            ],
            'rapport_soumis' => [
                'Rapport de visite soumis',
                "L'agent {$nomAgent} a soumis un rapport pour « {$bien?->titre} ».",
                'Rapport soumis',
                "Votre rapport pour « {$bien?->titre} » a bien été soumis.",
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

        if ($bien?->proprietaire && $msgProprio) {
            $emailHtml = EmailTemplateService::generic(
                titre: $titreProprio, intro: $msgProprio,
                rows:  [
                    ['icon' => '🏠', 'label' => 'Bien',  'value' => $bien->titre],
                    ['icon' => '📅', 'label' => 'Date',  'value' => $dateVisite ?? '—'],
                    ['icon' => '👤', 'label' => 'Agent', 'value' => $nomAgent],
                ],
            );
            $this->notifService->notify(
                user: $bien->proprietaire, type: 'visite_' . $request->input('statut'),
                titre: $titreProprio, message: $msgProprio, data: $dataCommon,
                emailSubject: "ImmoPro — {$titreProprio} : {$bien->titre}", emailBody: $emailHtml,
            );
        }

        if ($msgAgent) {
            $agentHtml = EmailTemplateService::generic(
                titre: $titreAgent, intro: $msgAgent,
                rows:  [
                    ['icon' => '🏠', 'label' => 'Bien', 'value' => $bien?->titre ?? ''],
                    ['icon' => '📅', 'label' => 'Date', 'value' => $dateVisite ?? '—'],
                ],
            );
            $this->notifService->notify(
                user: $agent, type: 'visite_' . $request->input('statut') . '_agent',
                titre: $titreAgent, message: $msgAgent, data: $dataCommon,
                emailSubject: "ImmoPro — {$titreAgent}", emailBody: $agentHtml,
            );
        }

        foreach (User::where('role', 'admin')->get() as $admin) {
            $msgAdmin = "L'agent {$nomAgent} a mis à jour la visite de « {$bien?->titre} » → {$request->input('statut')}.";
            $this->notifService->notify(
                $admin, 'visite_update_admin', 'Mise à jour de visite', $msgAdmin, $dataCommon,
                "ImmoPro — Visite mise à jour : {$bien?->titre}",
                EmailTemplateService::generic(
                    titre: 'Mise à jour de visite', intro: $msgAdmin,
                    rows: [
                        ['icon' => '🏠', 'label' => 'Bien',          'value' => $bien?->titre ?? ''],
                        ['icon' => '👤', 'label' => 'Agent',         'value' => $nomAgent],
                        ['icon' => '📅', 'label' => 'Date',          'value' => $dateVisite ?? '—'],
                        ['icon' => '📋', 'label' => 'Nouveau statut','value' => $request->input('statut')],
                    ],
                )
            );
        }

        // ── Broadcast temps réel ──────────────────────────────────────────────
        // Pas de ->toOthers() : l'agent doit aussi recevoir l'event pour que
        // sa propre page visites/calendrier se mette à jour en temps réel.
        try {
            broadcast(new VisiteStatutChanged($visite->load('bien')));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[AgentVisiteController] Broadcast update échoué: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Visite mise à jour.',
            'visite'  => $this->formatVisite($visite),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SECTION 4 : VISITES CLIENT (acheteur)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /api/agent/visites/clients
     * Liste toutes les visites client de l'agent (tous statuts actifs).
     * Supporte le filtre ?statut=proposee|en_attente_client|confirmee|annulee
     */
    public function visitesClients(Request $request): JsonResponse
    {
        $agent  = $request->user();
        $statut = $request->query('statut');

        $query = Visite::with(['bien.proprietaire', 'bien.medias', 'client'])
            ->where('agent_id', $agent->id)
            ->where('type_visite', Visite::TYPE_CLIENT);

        if ($statut) {
            $query->where('statut', $statut);
        } else {
            $query->whereIn('statut', [
                Visite::STATUT_PROPOSEE,
                Visite::STATUT_EN_ATTENTE_CLIENT,
                Visite::STATUT_INDISPONIBLE,
                Visite::STATUT_CONFIRMEE,
            ]);
        }

        $visites = $query->orderByDesc('created_at')
            ->get()
            ->map(fn ($v) => $this->formatVisiteClient($v));

        return response()->json(['success' => true, 'data' => $visites]);
    }

    /**
     * GET /api/agent/visites/clients/{id}
     * Détail complet d'une visite client : infos client, bien, proprio, créneaux.
     */
    public function showVisiteClient(Request $request, string $visiteId): JsonResponse
    {
        $agent = $request->user();

        $visite = Visite::with(['bien.proprietaire', 'bien.medias', 'client'])
            ->where('id', $visiteId)
            ->where('agent_id', $agent->id)
            ->where('type_visite', Visite::TYPE_CLIENT)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $this->formatVisiteClient($visite),
        ]);
    }

    /**
     * POST /api/agent/visites/{id}/proposer-creneaux
     * L'agent propose des créneaux au client (1 à N).
     * Supporte : saisie manuelle OU sélection depuis créneaux libres.
     * Propagation automatique de la durée.
     * Détection de conflits calendrier.
     */
    public function proposerCreneauxClient(Request $request, string $visiteId): JsonResponse
    {
        $agent = $request->user();

        $request->validate([
            'creneaux'                   => 'required|array|min:1|max:10',
            'creneaux.*.date_debut'      => 'required|date',
            'creneaux.*.duree_minutes'   => 'nullable|integer|min:15|max:480',
            'creneaux.*.creneau_libre_id'=> 'nullable|uuid|exists:creneaux_visite,id',
            'note'                       => 'nullable|string|max:500',
        ]);

        $visite = Visite::where('id', $visiteId)
            ->where('agent_id', $agent->id)
            ->where('type_visite', Visite::TYPE_CLIENT)
            ->whereIn('statut', [
                Visite::STATUT_PROPOSEE,
                Visite::STATUT_EN_ATTENTE_CLIENT,
                Visite::STATUT_INDISPONIBLE,
            ])
            ->with(['bien', 'client'])
            ->firstOrFail();

        $dureeRef = null;
        $creneaux = [];

        foreach ($request->creneaux as $item) {
            $debut = Carbon::parse($item['date_debut']);

            if ($debut->isBefore(now()->subMinutes(5))) {
                return response()->json([
                    'success' => false,
                    'message' => "Le créneau du {$debut->format('d/m/Y à H:i')} est dans le passé. Veuillez choisir un créneau futur.",
                ], 422);
            }

            if (isset($item['duree_minutes']) && $item['duree_minutes']) {
                $dureeRef = (int) $item['duree_minutes'];
            }
            $duree = $dureeRef ?? 60;

            // Vérifier conflit calendrier agent
            $conflit = $this->detecterConflitAgent($agent->id, $debut, $duree, $visiteId);
            if ($conflit) {
                return response()->json([
                    'success' => false,
                    'message' => "Conflit : le créneau du {$debut->format('d/m H:i')} est déjà occupé par une visite confirmée.",
                    'conflit' => $conflit,
                ], 409);
            }

            $creneaux[] = [
                'date_debut'      => $debut->toIso8601String(),
                'duree_minutes'   => $duree,
                'creneau_libre_id'=> $item['creneau_libre_id'] ?? null,
            ];
        }

        $visite->update([
            'creneaux_agent'     => $creneaux,
            'statut'             => Visite::STATUT_EN_ATTENTE_CLIENT,
            'notes'              => $request->input('note') ?: $visite->notes,
        ]);

        $bien     = $visite->bien;
        $client   = $visite->client;
        $nb       = count($creneaux);
        $nomAgent = trim("{$agent->first_name} {$agent->last_name}");

        if ($client) {
            $lignesCreneaux = collect($creneaux)->map(function ($c, $i) {
                $d = Carbon::parse($c['date_debut'])->locale('fr');
                return [
                    'icon'  => '📅',
                    'label' => 'Option ' . ($i + 1),
                    'value' => $d->isoFormat('ddd D MMM [à] HH[h]mm') . ' (' . $c['duree_minutes'] . ' min)',
                ];
            })->all();

            $html = EmailTemplateService::generic(
                titre: '📅 Créneaux de visite disponibles !',
                intro: "{$nomAgent} vous propose {$nb} créneau(x) pour visiter « {$bien?->titre} ». Choisissez celui qui vous convient.",
                rows:  array_merge(
                    [['icon' => '🏠', 'label' => 'Bien', 'value' => $bien?->titre ?? '']],
                    $lignesCreneaux
                ),
                outro: 'Connectez-vous à l\'application pour choisir votre créneau.',
            );
            $this->notifService->notify(
                $client, 'creneaux_agent_proposes',
                'Créneaux de visite proposés !',
                "{$nomAgent} vous propose {$nb} créneau(x) pour « {$bien?->titre} ».",
                ['visite_id' => $visite->id, 'bien_id' => $bien?->id, 'nb_creneaux' => $nb],
                "ImmoPro — Créneaux disponibles pour « {$bien?->titre} »", $html
            );
        }

        // ── Broadcast temps réel ──────────────────────────────────────────────
        // Note : on n'utilise PAS ->toOthers() ici car l'agent est lui-même
        // destinataire de l'event (sa page visites doit se mettre à jour).
        try {
            broadcast(new VisiteStatutChanged($visite->load('bien')));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[AgentVisiteController] Broadcast créneaux client échoué: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "{$nb} créneau(x) proposé(s). Le client sera notifié.",
            'data'    => $this->formatVisiteClient($visite->fresh(['bien.proprietaire', 'bien.medias', 'client'])),
        ]);
    }


    // ─────────────────────────────────────────────────────────────────────
    // SECTION 5 : PROPOSER CRÉNEAUX (ancienne route — vérification proprio)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * POST /api/agent/biens/{id}/visites/proposer
     * @deprecated Utiliser proposeCreneaux() à la place
     */
    public function proposer(Request $request, string $bienId): JsonResponse
    {
        $agent = $request->user();

        $request->validate([
            'creneaux'              => 'required|array|min:1|max:3',
            'creneaux.*.date'       => 'required|date|after_or_equal:today',
            'creneaux.*.heure_debut'=> 'required|date_format:H:i',
            'notes'                 => 'nullable|string|max:500',
        ]);

        $bien = Bien::where('id', $bienId)
            ->where('agent_id', $agent->id)
            ->whereIn('statut', ['en_cours', 'en_attente'])
            ->with(['proprietaire'])
            ->firstOrFail();

        $existing = Visite::where('bien_id', $bienId)
            ->whereIn('statut', ['en_attente', 'planifiee', 'confirmee'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Une visite est déjà en cours de planification pour ce bien.',
                'visite'  => $this->formatVisite($existing),
            ], 409);
        }

        $visite = Visite::create([
            'bien_id'           => $bienId,
            'agent_id'          => $agent->id,
            'creneaux_proposes' => $request->input('creneaux'),
            'notes'             => $request->input('notes'),
            'statut'            => 'en_attente',
        ]);

        $bien->update(['last_activity_at' => now()]);
        $visite->load(['bien', 'agent']);

        if ($bien->proprietaire) {
            $bien->proprietaire->notify(
                new \App\Notifications\CreneauxProposesNotification($visite)
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Les créneaux de visite ont été proposés au propriétaire.',
            'visite'  => $this->formatVisite($visite),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SECTION 6 : HELPERS PRIVÉS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Détecte un conflit dans le calendrier de l'agent.
     * Vérifie si la plage [debut, debut+duree] chevauche une visite confirmée.
     *
     * @param  string      $agentId
     * @param  Carbon      $debut
     * @param  int         $dureeMinutes
     * @param  string|null $excludeVisiteId  ignorer cette visite (re-proposition)
     */
    private function detecterConflitAgent(
        string $agentId,
        Carbon $debut,
        int    $dureeMinutes,
        string $excludeVisiteId = null
    ): ?array {
        $fin = $debut->copy()->addMinutes($dureeMinutes);

        $query = Visite::where('agent_id', $agentId)
            ->where('statut', Visite::STATUT_CONFIRMEE)
            ->whereNotNull('date_visite');

        if ($excludeVisiteId) {
            $query->where('id', '!=', $excludeVisiteId);
        }

        $conflit = $query->where(function ($q) use ($debut, $fin) {
            $q->where('date_visite', '<', $fin)
              ->whereRaw('DATE_ADD(date_visite, INTERVAL duree_minutes MINUTE) > ?', [$debut]);
        })->with('bien')->first();

        if (! $conflit) {
            return null;
        }

        return [
            'visite_id'    => $conflit->id,
            'bien_titre'   => $conflit->bien?->titre,
            'date_visite'  => $conflit->date_visite?->toIso8601String(),
            'duree_minutes'=> $conflit->duree_minutes,
        ];
    }

    private function formatVisite(Visite $v): array
    {
        return [
            'id'                      => $v->id,
            'date_visite'             => $v->date_visite?->toIso8601String(),
            'duree_minutes'           => $v->duree_minutes,
            'notes'                   => $v->notes,
            'statut'                  => $v->statut,
            'rapport'                 => $v->rapport,
            'visite_effectuee'        => $v->visite_effectuee,
            'confirme_par_proprio_le' => $v->confirme_par_proprio_le?->toIso8601String(),
            'created_at'              => $v->created_at->toIso8601String(),
        ];
    }

    private function formatVisiteClient(Visite $v): array
    {
        $bien   = $v->bien;
        $client = $v->client;

        // Photo principale du bien
        $photoPrincipale = $bien?->medias()
            ->where('type', 'photo')
            ->orderByDesc('est_principale')
            ->orderBy('ordre')
            ->first();

        // Infos propriétaire du bien
        $proprio    = $bien?->proprietaire;
        $nomProprio = null;
        $telProprio = null;
        if ($proprio) {
            $nomProprio = trim("{$proprio->first_name} {$proprio->last_name}");
            $telProprio = $proprio->telephone ?? null;
        } elseif ($bien) {
            $nomProprio = trim(($bien->proprietaire_prenom ?? '') . ' ' . ($bien->proprietaire_nom ?? '')) ?: null;
            $telProprio = $bien->proprietaire_telephone ?? null;
        }

        return [
            'id'                    => $v->id,
            'bien_id'               => $v->bien_id,
            'bien_titre'            => $bien?->titre,
            'bien_adresse'          => $bien?->adresse,
            'bien_type'             => $bien?->type_bien,
            'bien_type_transaction' => $bien?->type_transaction,
            'bien_prix'             => $bien ? (float) $bien->prix : null,
            'bien_photo'            => $photoPrincipale?->url,
            'date_visite'           => $v->date_visite?->toIso8601String(),
            'duree_minutes'         => $v->duree_minutes,
            'statut'                => $v->statut,
            'est_payee'             => $v->est_payee,
            'creneaux_agent'        => $v->creneaux_agent ?? [],
            'notes'                 => $v->notes,
            'nb_indisponibilites'   => $v->nb_indisponibilites ?? 0,
            'note_indisponibilite'  => $v->note_indisponibilite,
            // Infos client demandeur
            'client'                => $client ? [
                'id'        => $client->id,
                'nom'       => trim("{$client->first_name} {$client->last_name}"),
                'email'     => $client->email,
                'telephone' => $client->telephone ?? null,
                'photo'     => $client->profile_picture ?? null,
            ] : null,
            // Infos propriétaire du bien
            'proprietaire'          => [
                'id'        => $proprio?->id,
                'nom'       => $nomProprio,
                'email'     => $proprio?->email ?? $bien?->proprietaire_email,
                'telephone' => $telProprio,
            ],
            'created_at'            => $v->created_at->toIso8601String(),
        ];
    }

    private function formatCreneau(CreneauVisite $c): array
    {
        $duree = ($c->date_debut && $c->date_fin)
            ? (int) $c->date_debut->diffInMinutes($c->date_fin)
            : null;

        return [
            'id'            => $c->id,
            'bien_id'       => $c->bien_id,
            'bien_titre'    => $c->bien?->titre,
            'date_debut'    => $c->date_debut?->toIso8601String(),
            'date_fin'      => $c->date_fin?->toIso8601String(),
            'duree_minutes' => $duree > 0 ? $duree : 60, // fallback 60 min si calcul invalide
            'statut'        => $c->statut,
            'visite_id'     => $c->visite_id,
        ];
    }
}
