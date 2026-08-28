<?php

namespace App\Http\Controllers\Client;

use App\Events\BienStatutChanged;
use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Services\BienDescriptionService;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
    private BienDescriptionService $descriptionService;

    public function __construct(BienDescriptionService $descriptionService)
    {
        $this->descriptionService = $descriptionService;
    }

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
                'retire'          => $counts['retire']          ?? 0,
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
    // POST /api/proprietaire/biens/{id}/publier
    // Le propriétaire publie son bien après approbation admin (statut "valide")
    // ─────────────────────────────────────────────────────────────────────────

    public function publier(Request $request, string $id): JsonResponse
    {
        $bien = Bien::where('user_id', $request->user()->id)
            ->where('statut', 'valide')
            ->findOrFail($id);

        $bien->update([
            'statut'    => 'publie',
            'publie_le' => now(),
        ]);

        // ── Notifier le propriétaire (push + email) ───────────────────────────
        try {
            $user = $request->user();
            $emailBody = EmailTemplateService::generic(
                titre: 'Votre bien est maintenant publié',
                intro: "Félicitations ! Votre annonce est désormais en ligne sur la plateforme ImmoPro et visible par tous les acheteurs et locataires potentiels.",
                rows: [
                    ['icon' => 'home',   'label' => 'Bien',      'value' => $bien->titre],
                    ['icon' => 'pin',    'label' => 'Adresse',   'value' => $bien->adresse],
                    ['icon' => 'type',   'label' => 'Type',      'value' => ucfirst($bien->type_transaction)],
                    ['icon' => 'status', 'label' => 'Statut',    'value' => 'Publié'],
                    ['icon' => 'cal',    'label' => 'Publié le', 'value' => now()->format('d/m/Y à H:i')],
                ],
                outro: 'Votre annonce est maintenant visible par des milliers d\'utilisateurs. Bonne chance pour votre transaction !'
            );

            app(NotificationService::class)->notify(
                $user,
                'bien_publie',
                'Votre bien est en ligne',
                "Votre bien \"{$bien->titre}\" est maintenant publié sur la plateforme et visible par tous.",
                ['bien_id' => (string) $bien->id],
                'Votre annonce est publiée — ImmoPro',
                $emailBody,
            );
        } catch (\Throwable $e) {
            Log::warning('[ProprietaireBien] Notification publication échouée: ' . $e->getMessage());
        }

        // ── Broadcast temps réel — notifier admins et agents ──────────────────
        try {
            broadcast(new BienStatutChanged($bien->fresh()))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('[ProprietaireBien] Broadcast publication échouée: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Votre bien est maintenant publié sur la plateforme ! 🎉',
            'data'    => $this->formatBienDetail($bien->fresh(['medias'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/proprietaire/biens/{id}/retirer
    // Le propriétaire retire son bien de la publication (motif obligatoire).
    // Passe le statut de 'publie' → 'retire' (dépublié, toujours en base).
    // ─────────────────────────────────────────────────────────────────────────

    public function retirer(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'motif' => 'nullable|string|max:300',
        ], [
            'motif.max'      => 'Le motif ne peut pas dépasser 300 caractères.',
        ]);

        $bien = Bien::where('user_id', $request->user()->id)
            ->where('statut', 'publie')
            ->findOrFail($id);

        $bien->update([
            'statut'     => 'retire',
            'note_admin' => '[RETRAIT PROPRIÉTAIRE] ' . ($request->input('motif') ?: 'Aucun motif fourni'),
        ]);

        // ── Notifier le propriétaire ──────────────────────────────────────────
        try {
            $user      = $request->user();
            $emailBody = EmailTemplateService::generic(
                titre: 'Publication retirée',
                intro: "Votre demande de retrait a bien été prise en compte. Votre annonce n'est plus visible sur la plateforme, mais votre bien est conservé dans votre espace.",
                rows: [
                    ['icon' => 'home',   'label' => 'Bien',    'value' => $bien->titre],
                    ['icon' => 'pin',    'label' => 'Adresse', 'value' => $bien->adresse],
                    ['icon' => 'note',   'label' => 'Motif',   'value' => $request->input('motif') ?: 'Aucun motif fourni'],
                    ['icon' => 'status', 'label' => 'Statut',  'value' => 'Retiré de la publication'],
                ],
                outro: 'Votre bien est toujours enregistré. Vous pouvez contacter le support si vous souhaitez le republier.'
            );

            app(NotificationService::class)->notify(
                $user,
                'bien_retire',
                'Publication retirée',
                "Votre bien \"{$bien->titre}\" a été retiré de la publication. Il n'est plus visible sur la plateforme.",
                ['bien_id' => (string) $bien->id],
                'Publication retirée — ImmoPro',
                $emailBody,
            );
        } catch (\Throwable $e) {
            Log::warning('[ProprietaireBien] Notification retrait échouée: ' . $e->getMessage());
        }

        // ── Broadcast temps réel ──────────────────────────────────────────────
        try {
            broadcast(new BienStatutChanged($bien->fresh()))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('[ProprietaireBien] Broadcast retrait échouée: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Votre annonce a été retirée de la publication. Elle reste enregistrée dans votre espace.',
            'data'    => $this->formatBienDetail($bien->fresh(['medias'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/proprietaire/biens/{id}
    // Détail complet d'un bien (avec note_admin / raison_rejet + visite vérif)
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

        // Merge des champs déposant dans les caractéristiques pour la couche mobile
        $caracteristiques = $b->caracteristiques ?? [];
        $caracteristiques['role_deposant']              = $b->role_deposant;
        $caracteristiques['proprietaire_nom']           = $b->proprietaire_nom;
        $caracteristiques['proprietaire_prenom']        = $b->proprietaire_prenom;
        $caracteristiques['proprietaire_sexe']          = $b->proprietaire_sexe;
        $caracteristiques['proprietaire_nationalite']   = $b->proprietaire_nationalite;
        $caracteristiques['proprietaire_telephone']     = $b->proprietaire_telephone;
        $caracteristiques['proprietaire_email']         = $b->proprietaire_email;
        $caracteristiques['proprietaire_adresse']       = $b->proprietaire_adresse;
        $caracteristiques['unite_prix']                 = $b->unite_prix;
        $caracteristiques['avance_mois']                = $b->avance_mois;
        $caracteristiques['caution']                    = $b->caution ? (float) $b->caution : null;
        $caracteristiques['superficie']                 = $b->superficie ? (float) $b->superficie : null;
        // Nettoyer les nulls pour ne pas polluer le JSON
        $caracteristiques = array_filter($caracteristiques, fn($v) => $v !== null);

        return [
            'id'               => $b->id,
            'titre'            => $b->titre,
            'description'      => $this->descriptionService->construire($b),
            'prix'             => $b->prix !== null ? (float) $b->prix : null,
            'unite_prix'       => $b->unite_prix,
            'avance_mois'      => $b->avance_mois,
            'caution'          => $b->caution ? (float) $b->caution : null,
            'adresse'          => $b->adresse,
            'latitude'         => $b->latitude ? (float) $b->latitude : null,
            'longitude'        => $b->longitude ? (float) $b->longitude : null,
            'type_bien'        => $b->type_bien,
            'categorie_nom'    => $b->getCategorie()?->nom ?? ($b->type_bien ? ucfirst(str_replace('_', ' ', $b->type_bien)) : null),
            'type_transaction' => $b->type_transaction,
            'statut'           => $this->normalizeStatut($b->statut),
            'frais_etude_statut' => $b->frais_etude_statut,
            'surface'          => $b->surface ? (float) $b->surface : null,
            'superficie'       => $b->superficie ? (float) $b->superficie : null,
            'nb_pieces'        => $b->nb_pieces,
            'nb_salles_bain'   => $b->nb_salles_bain,
            'caracteristiques' => $caracteristiques,
            'role_deposant'    => $b->role_deposant,
            // Champs déposant exposés directement au niveau racine
            'proprietaire_nom'           => $b->proprietaire_nom,
            'proprietaire_prenom'        => $b->proprietaire_prenom,
            'proprietaire_sexe'          => $b->proprietaire_sexe,
            'proprietaire_nationalite'   => $b->proprietaire_nationalite,
            'proprietaire_telephone'     => $b->proprietaire_telephone,
            'proprietaire_email'         => $b->proprietaire_email,
            'proprietaire_adresse'       => $b->proprietaire_adresse,
            'photo_principale' => $photo ? ($photo->url ?? null) : null,
            'medias'           => $b->medias->map(fn ($m) => [
                'id'             => $m->id,
                'type'           => $m->type === 'photo' ? 'image' : $m->type,
                'url'            => $m->url,
                'est_principale' => (bool) $m->est_principale,
                'ordre'          => $m->ordre,
            ])->values()->toArray(),
            'raison_rejet'     => $b->statut === 'rejete' ? $b->note_admin : null,
            'publication_auto' => (bool) ($b->publication_auto ?? true),
            'publie_le'        => $b->publie_le?->toIso8601String(),
            'created_at'       => $b->created_at->toIso8601String(),
        ];
    }

    /**
     * Format complet pour le détail — inclut la visite de vérification + documents (brouillons).
     */
    private function formatBienDetail(Bien $b): array
    {
        $base = $this->formatBien($b);

        // Visite de vérification liée à ce bien (type_visite = verification)
        $visite = \App\Models\Visite::where('bien_id', $b->id)
            ->where('type_visite', \App\Models\Visite::TYPE_VERIFICATION)
            ->with(['agent'])
            ->latest()
            ->first();

        $base['visite_verification'] = $visite ? $this->formatVisite($visite) : null;

        // Documents — exposés pour permettre la restauration du brouillon côté mobile
        // On ne retourne que les slugs (pas les chemins locaux) pour indiquer qu'un doc existe
        $base['documents_brouillon'] = $b->documents->map(fn ($d) => [
            'type'         => $d->type,
            'nom_original' => $d->nom_original,
            'statut'       => $d->statut,
        ])->values()->toArray();

        return $base;
    }

    /**
     * Formate une visite de vérification pour le propriétaire.
     * Les créneaux peuvent provenir soit de creneaux_agent (JSON sur visite)
     * soit de la table creneaux_visite (créés via l'interface web agent).
     */
    private function formatVisite(\App\Models\Visite $v): array
    {
        // Priorité 1 : créneaux JSON sur la visite (flow API agent → client)
        $creneauxJson = collect($v->creneaux_agent ?? [])
            ->values()
            ->map(fn ($c, $i) => [
                'index'         => $i,
                'date_debut'    => $c['date_debut'] ?? null,
                'duree_minutes' => (int) ($c['duree_minutes'] ?? 60),
            ])
            ->filter(fn ($c) => $c['date_debut'] !== null)
            ->values()
            ->toArray();

        // Priorité 2 : table creneaux_visite (flow interface web agent)
        $creneauxTable = [];
        if (empty($creneauxJson)) {
            $creneauxTable = \App\Models\CreneauVisite::where('bien_id', $v->bien_id)
                ->where('statut', 'disponible')
                ->orderBy('date_debut')
                ->get()
                ->values()
                ->map(fn ($c, $i) => [
                    'index'         => $i,
                    'date_debut'    => $c->date_debut?->toIso8601String(),
                    'duree_minutes' => $c->date_debut && $c->date_fin
                        ? (int) $c->date_debut->diffInMinutes($c->date_fin)
                        : 60,
                ])
                ->filter(fn ($c) => $c['date_debut'] !== null)
                ->values()
                ->toArray();
        }

        $creneaux = !empty($creneauxJson) ? $creneauxJson : $creneauxTable;

        // Statut effectif : si des créneaux existent dans la table mais que la visite
        // n'est pas encore en_attente_client, on le force pour l'affichage mobile
        $statut = $v->statut;
        if ($statut === 'proposee' && !empty($creneauxTable)) {
            $statut = 'en_attente_client';
        }
        // Le statut 'indisponible' est toujours transmis tel quel

        return [
            'id'                  => $v->id,
            'statut'              => $statut,
            'visite_effectuee'    => (bool) ($v->visite_effectuee ?? false),
            'date_visite'         => $v->date_visite?->toIso8601String(),
            'duree_minutes'       => $v->duree_minutes,
            'notes'               => $v->notes,
            'creneaux'            => $creneaux,
            'nb_indisponibilites' => $v->nb_indisponibilites ?? 0,
            'agent_nom'           => $v->agent
                ? trim("{$v->agent->first_name} {$v->agent->last_name}")
                : null,
            'created_at'          => $v->created_at->toIso8601String(),
        ];
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
            'valide'     => 'valide',
            'retire'     => 'retire',
            default      => $statut,
        };
    }
}
