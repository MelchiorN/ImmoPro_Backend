<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BienListResource;
use App\Http\Resources\BienResource;
use App\Models\Bien;
use App\Models\User;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BienAdminController extends Controller
{
    public function __construct(private readonly NotificationService $notifService) {}

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
        ]);

        $bien = Bien::findOrFail($id);

        // Transitions autorisées
        // L'admin approuve → statut "valide" (le propriétaire publie lui-même ensuite)
        $transitionsAutorisees = [
            'en_attente' => ['valide', 'rejete'],
            'en_cours'   => ['valide', 'rejete'],
            'valide'     => ['archive', 'rejete'],
            'rejete'     => ['valide'],
            'publie'     => ['archive', 'rejete'],
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
        }

        if ($nouveauStatut === 'rejete') {
            $payload['note_admin'] = $request->input('note_admin', 'Votre annonce a été rejetée.');
            $payload['publie_le']  = null;
        }

        $bien->update($payload);

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
                titre:   $nouveauStatut === 'valide' ? '🎉 Annonce approuvée — publiez-la !' : "Annonce {$statutLabel}",
                intro:   $messageMsg,
                rows:    [
                    ['icon' => '🏠', 'label' => 'Bien',   'value' => $bien->titre],
                    ['icon' => '📋', 'label' => 'Statut', 'value' => $nouveauStatut === 'valide' ? 'Approuvé' : ucfirst($nouveauStatut)],
                ],
                noteBox: ($nouveauStatut === 'rejete' && $request->input('note_admin'))
                    ? $request->input('note_admin')
                    : null,
            );

            $this->notifService->notify(
                user:         $bien->proprietaire,
                type:         "bien_{$nouveauStatut}",
                titre:        $nouveauStatut === 'valide' ? 'Annonce approuvée 🎉' : "Annonce {$statutLabel}",
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
                    titre:   $nouveauStatut === 'valide' ? 'Bien approuvé ✅' : 'Statut du bien modifié',
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

        // ── Notifier l'agent assigné ──────────────────────────────────────────
        $agentUser = User::find($request->input('agent_id'));
        if ($agentUser) {
            $emailHtml = EmailTemplateService::generic(
                titre: '📋 Nouveau bien à traiter',
                intro: "Un nouveau bien immobilier vous a été assigné par l'administration.",
                rows:  [
                    ['icon' => '🏠', 'label' => 'Bien',    'value' => $bien->titre],
                    ['icon' => '📍', 'label' => 'Adresse', 'value' => $bien->adresse ?? '—'],
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
        }

        return response()->json([
            'success' => true,
            'message' => 'Bien attribué à l\'agent.',
            'data'    => new BienResource($bien->fresh(['medias', 'documents', 'proprietaire'])),
        ]);
    }
}
