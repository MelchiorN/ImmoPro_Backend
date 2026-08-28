<?php

namespace App\Http\Controllers\Admin;

use App\Events\BienStatutChanged;
use App\Events\DossierAssigneEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\BienListResource;
use App\Http\Resources\BienResource;
use App\Models\Bien;
use App\Models\User;
use App\Services\BienDescriptionService;
use App\Services\EmailTemplateService;
use App\Services\GeminiService;
use App\Services\NotificationService;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BienAdminController extends Controller
{
    public function __construct(
        private readonly NotificationService    $notifService,
        private readonly WorkflowService        $workflowService,
        private readonly GeminiService          $gemini,
        private readonly BienDescriptionService $descService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/biens
    // Liste tous les biens avec filtre statut (admin/agent)
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Bien::with(['medias', 'proprietaire', 'rapport', 'agent'])
            ->when(
                $request->query('statut'),
                fn ($q, $s) => $q->where('statut', $s),
                fn ($q)     => $q->whereIn('statut', ['en_attente', 'en_cours', 'publie', 'rejete'])
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
    // GET /api/admin/biens/counts
    // Compteurs par statut — utilisé par l'AdminSidebar pour les badges
    // ─────────────────────────────────────────────────────────────────────────

    public function counts(): JsonResponse
    {
        $raw = Bien::whereIn('statut', ['en_attente', 'en_cours', 'publie', 'rejete', 'retire', 'valide'])
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        return response()->json([
            'success' => true,
            'data'    => [
                'en_attente' => $raw['en_attente'] ?? 0,
                'en_cours'   => $raw['en_cours']   ?? 0,
                'publie'     => $raw['publie']      ?? 0,
                'rejete'     => $raw['rejete']      ?? 0,
                'retire'     => $raw['retire']      ?? 0,
                'valide'     => $raw['valide']      ?? 0,
                // Dossiers actifs = en_attente (non assignés) + en_cours (en traitement)
                'actifs'     => ($raw['en_attente'] ?? 0) + ($raw['en_cours'] ?? 0),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/biens/{id}
    // Détail complet (admin/agent)
    // ─────────────────────────────────────────────────────────────────────────

    public function show(string $id): JsonResponse
    {
        $bien = Bien::with(['medias', 'documents', 'proprietaire', 'agent', 'rapport'])
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
            'statut'     => 'required|in:valide,rejete,archive',
            'note_admin' => 'nullable|string|max:1000',
            'prix_visite'=> 'nullable|numeric|min:0',
        ]);

        $bien = Bien::with(['categorie'])->findOrFail($id);

        // Transitions autorisées
        // L'admin approuve → statut "valide" (le propriétaire publie lui-même ensuite)
        $transitionsAutorisees = [
            'en_attente' => ['valide', 'rejete'],
            'en_cours'   => ['valide', 'rejete'],
            'valide'     => ['archive', 'rejete'],
            'rejete'     => ['valide'],
            'publie'     => ['archive', 'rejete'],
            'retire'     => ['valide', 'archive'],  // Bien retiré par le propriétaire : l'admin peut le remettre valide ou l'archiver
            'archive'    => ['valide'],
        ];

        $statutActuel  = $bien->statut;
        $nouveauStatut = $request->input('statut');

        if (! in_array($nouveauStatut, $transitionsAutorisees[$statutActuel] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => "Transition de statut invalide : {$statutActuel} → {$nouveauStatut}.",
            ], 422);
        }

        $payload = ['statut' => $nouveauStatut];

        if ($nouveauStatut === 'valide') {
            $payload['note_admin'] = null;
            // Si le tarif visite est fourni explicitement, l'utiliser
            if ($request->filled('prix_visite')) {
                $payload['prix_visite'] = (float) $request->input('prix_visite');
            }
            // Sinon calculer automatiquement depuis la catégorie si le type est "pourcentage"
            elseif (! $bien->prix_visite && $bien->categorie) {
                $cat = $bien->categorie;
                if ($cat->visite_tarif_type === 'pourcentage' && $cat->visite_pourcentage > 0) {
                    $payload['prix_visite'] = $cat->calculerPrixVisite((float) $bien->prix);
                } elseif ($cat->visite_tarif_type === 'fixe_manuel' && $cat->visite_tarif_fixe > 0) {
                    $payload['prix_visite'] = (float) $cat->visite_tarif_fixe;
                }
            }
        }

        if ($nouveauStatut === 'rejete') {
            $payload['note_admin'] = $request->input('note_admin', 'Votre annonce a été rejetée.');
            $payload['publie_le']  = null;
        }

        $bien->update($payload);

        // ── Générer et mettre en cache la description Gemini ─────────────────
        // Uniquement lors du passage en "valide" et si la desc n'existe pas encore.
        // Silencieux : une erreur Gemini ne bloque jamais le workflow d'approbation.
        if ($nouveauStatut === 'valide' && empty($bien->desc_personnalisee)) {
            try {
                $descBrute       = $this->descService->construire($bien);
                $descPersonnalisee = $this->gemini->enrichirDescription($descBrute, $bien->toArray());
                $bien->update(['desc_personnalisee' => $descPersonnalisee]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[BienAdmin] Génération desc Gemini échouée (non bloquante)', [
                    'bien_id' => $bien->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // ── Notifier le propriétaire du changement de statut ──────────────────
        if ($bien->proprietaire) {
            $statutLabel = match ($nouveauStatut) {
                'valide'  => 'approuvé ✅',
                'rejete'  => 'rejeté',
                'archive' => 'archivé',
                default   => $nouveauStatut,
            };

            $messageMsg = $nouveauStatut === 'valide'
                ? "Bonne nouvelle ! Votre annonce « {$bien->titre} » a été approuvée. Connectez-vous pour la publier sur la plateforme."
                : "Votre annonce « {$bien->titre} » a été {$statutLabel}.";

            if ($nouveauStatut === 'rejete' && $request->input('note_admin')) {
                $messageMsg .= " Motif : " . $request->input('note_admin');
            }

            $emailHtml = EmailTemplateService::generic(
                titre:   $nouveauStatut === 'valide' ? 'Annonce approuvée — publiez-la' : "Annonce {$statutLabel}",
                intro:   $messageMsg,
                rows:    [
                    ['icon' => 'home',   'label' => 'Bien',   'value' => $bien->titre],
                    ['icon' => 'status', 'label' => 'Statut', 'value' => $nouveauStatut === 'valide' ? 'Approuvé' : ucfirst($nouveauStatut)],
                ],
                noteBox: ($nouveauStatut === 'rejete' && $request->input('note_admin'))
                    ? $request->input('note_admin')
                    : null,
            );

            $this->notifService->notify(
                user:         $bien->proprietaire,
                type:         "bien_{$nouveauStatut}",
                titre:        $nouveauStatut === 'valide' ? 'Annonce approuvée' : "Annonce {$statutLabel}",
                message:      $messageMsg,
                data:         ['bien_id' => $bien->id, 'bien_titre' => $bien->titre, 'statut' => $nouveauStatut],
                emailSubject: $nouveauStatut === 'valide'
                    ? "ImmoPro — Votre annonce est approuvée, publiez-la !"
                    : "ImmoPro — Votre annonce a été {$statutLabel}",
                emailBody:    $emailHtml,
            );
        }

        // ── Notifier l'agent assigné si présent ───────────────────────────────
        if ($bien->agent_id) {
            $agentUser = User::find($bien->agent_id);
            if ($agentUser) {
                $msgAgent = $nouveauStatut === 'valide'
                    ? "Le bien « {$bien->titre} » a été approuvé par l'administration. Le propriétaire va être invité à le publier."
                    : "Le statut du bien « {$bien->titre} » a changé : {$statutActuel} → {$nouveauStatut}.";

                $this->notifService->notify(
                    user:    $agentUser,
                    type:    "bien_statut_change_agent",
                    titre:   $nouveauStatut === 'valide' ? 'Bien approuvé' : 'Statut du bien modifié',
                    message: $msgAgent,
                    data:    ['bien_id' => $bien->id, 'bien_titre' => $bien->titre, 'statut' => $nouveauStatut],
                );
            }
        }

        // Log d'activité
        activity()
            ->causedBy($request->user())
            ->performedOn($bien)
            ->withProperties(['ancien_statut' => $statutActuel, 'nouveau_statut' => $nouveauStatut])
            ->log("Statut du bien changé : {$statutActuel} → {$nouveauStatut}");

        // ── Broadcast temps réel ──────────────────────────────────────────────
        broadcast(new BienStatutChanged($bien->fresh()))->toOthers();

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

        // Valider que l'agent existe et a bien le rôle 'agent'
        $agentUser = User::where('id', $request->input('agent_id'))
                         ->where('role', 'agent')
                         ->first();

        if (! $agentUser) {
            return response()->json([
                'success' => false,
                'message' => 'Cet utilisateur n\'est pas un agent valide.',
            ], 422);
        }

        $bien = Bien::where('statut', 'en_attente')->findOrFail($id);

        $bien->update([
            'agent_id'   => $agentUser->id,
            'statut'     => 'en_cours',
            'claimed_at' => now(),
        ]);

        // ── Notifier l'agent assigné ──────────────────────────────────────────
        $emailHtml = EmailTemplateService::generic(
            titre: 'Nouveau bien à traiter',
            intro: "Un nouveau bien immobilier vous a été assigné par l'administration.",
            rows:  [
                ['icon' => 'home', 'label' => 'Bien',    'value' => $bien->titre],
                ['icon' => 'pin',  'label' => 'Adresse', 'value' => $bien->adresse ?? '—'],
            ],
        );

        $this->notifService->notify(
            user:         $agentUser,
            type:         'bien_assigne',
            titre:        'Nouveau bien assigné',
            message:      "Le bien « {$bien->titre} » vous a été assigné par l'administration.",
            data:         ['bien_id' => $bien->id, 'bien_titre' => $bien->titre],
            emailSubject: "ImmoPro — Nouveau bien à traiter : {$bien->titre}",
            emailBody:    $emailHtml,
        );

        // ── Notifier le propriétaire ──────────────────────────────────────────
        $proprietaire = $bien->proprietaire ?? User::find($bien->user_id);
        if ($proprietaire && $agentUser) {
            $emailHtml = EmailTemplateService::generic(
                titre: 'Votre dossier est pris en charge',
                intro: "Votre bien immobilier est maintenant en cours de traitement par un agent.",
                rows:  [
                    ['icon' => 'home', 'label' => 'Bien',  'value' => $bien->titre],
                    ['icon' => 'user', 'label' => 'Agent', 'value' => $agentUser->first_name . ' ' . $agentUser->last_name],
                ],
            );

            $this->notifService->notify(
                user:         $proprietaire,
                type:         'dossier_pris_en_charge',
                titre:        'Dossier pris en charge',
                message:      "Votre bien « {$bien->titre} » est maintenant pris en charge par un agent.",
                data:         ['bien_id' => $bien->id, 'bien_titre' => $bien->titre],
                emailSubject: "ImmoPro — Votre dossier est en cours de traitement",
                emailBody:    $emailHtml,
            );
        }

        // ── Broadcast temps réel ──────────────────────────────────────────────
        broadcast(new DossierAssigneEvent($bien->fresh(), $agentUser))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Bien attribué à l\'agent. Le statut est passé en cours.',
            'data'    => new BienResource($bien->fresh(['medias', 'documents', 'proprietaire', 'agent'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/biens/{id}/workflow
    // Suivi de progression du bien — lecture seule, accès tous biens.
    // ─────────────────────────────────────────────────────────────────────────

    public function workflow(string $id): JsonResponse
    {
        $bien = Bien::with(['agent', 'rapport'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->workflowService->calculer($bien),
        ]);
    }
}
