<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Bien\BienController;
use App\Http\Requests\StoreBienRequest;
use App\Models\Bien;
use App\Models\Categorie;
use App\Models\ConfigPublication;
use App\Models\Paiement;
use App\Models\Recu;
use App\Services\EmailTemplateService;
use App\Services\Payment\SemoaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gestion du paiement des frais d'étude de dossier avant soumission d'un bien.
 *
 * Flux :
 *  1. POST /api/client/frais-etude/initier  → calcule le montant, crée un Paiement,
 *     déclenche le PUSH Semoa, retourne paiement_id + montant
 *  2. POST /api/client/frais-etude/confirmer → vérifie le paiement Semoa,
 *     crée le bien, décrémente le quota, génère le reçu
 *  3. GET  /api/client/frais-etude/historique → liste tous les paiements de frais
 */
class FraisEtudeController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/client/frais-etude/initier
    // Initie le paiement des frais d'étude pour un dossier à soumettre.
    // Le payload du bien est transmis dans le même multipart que le paiement.
    // ─────────────────────────────────────────────────────────────────────────

    public function initier(StoreBienRequest $request): JsonResponse
    {
        $user   = $request->user();
        $config = ConfigPublication::instance();

        // ── Vérifier quota ───────────────────────────────────────────────────
        if (! $user->peutPublier()) {
            return response()->json([
                'success' => false,
                'code'    => 'QUOTA_EPUISE',
                'message' => 'Vous n\'avez plus de publications disponibles. Souscrivez à un abonnement.',
            ], 403);
        }

        // ── Vérifier que les frais sont actifs ───────────────────────────────
        if (! $config->frais_etude_actifs) {
            return response()->json([
                'success' => false,
                'code'    => 'FRAIS_DESACTIVES',
                'message' => 'Les frais d\'étude sont désactivés. Utilisez POST /api/biens pour soumettre directement.',
            ], 422);
        }

        // ── Calculer le montant ───────────────────────────────────────────────
        $typeBien  = $request->input('type_bien');
        $categorie = Categorie::where('slug', $typeBien)->where('actif', true)->first();

        if (! $categorie) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie introuvable.',
            ], 422);
        }

        $montant = $categorie->calculerFraisEtude((float) $request->input('prix'));

        if ($montant <= 0) {
            // Frais = 0 pour cette catégorie → créer directement
            $bienController = app(BienController::class);
            return $bienController->creerBien($request, $user, 'non_requis');
        }

        // ── Valider opérateur paiement ────────────────────────────────────────
        $request->validate([
            'operateur_paiement' => 'required|string|in:TMONEY,FLOOZ,CARD,tmoney,flooz,card',
            'telephone'          => [
                'required_if:operateur_paiement,TMONEY,FLOOZ,tmoney,flooz',
                'nullable', 'string', 'regex:/^(\+?228)?[79]\d{7}$/',
            ],
        ], [
            'telephone.required_if' => 'Le numéro Mobile Money est obligatoire.',
            'telephone.regex'       => 'Numéro invalide (ex: 90123456 ou +22890123456).',
        ]);

        $operateur = strtoupper($request->input('operateur_paiement'));
        $telephone = trim($request->input('telephone', ''));

        if ($telephone !== '' && ! str_starts_with($telephone, '+')) {
            $telephone = str_starts_with($telephone, '228')
                ? '+' . $telephone
                : '+228' . $telephone;
        }

        // ── Idempotence : éviter double paiement en moins de 15 min ──────────
        $existing = Paiement::where('type_paiement', 'frais_etude')
            ->where('statut', 'initie')
            ->whereHasMorph('payable', [\App\Models\Bien::class], fn ($q) => $q->where('user_id', $user->id)
                ->where('type_bien', $typeBien)
                ->where('frais_etude_statut', 'en_attente_paiement'))
            ->where('created_at', '>=', now()->subMinutes(15))
            ->latest()->first();

        if ($existing) {
            $instructionsExisting = match($operateur) {
                'TMONEY' => "Notification PUSH T-Money envoyée. Confirmez le paiement de " . number_format($montant, 0, ',', ' ') . " FCFA.",
                'FLOOZ'  => "Notification PUSH Flooz envoyée. Confirmez le paiement de " . number_format($montant, 0, ',', ' ') . " FCFA.",
                'CARD'   => "Rendez-vous sur le portail de paiement sécurisé.",
                default  => "Suivez les instructions de votre opérateur.",
            };

            return response()->json([
                'success' => true,
                'message' => 'Un paiement est déjà en cours pour ce dossier.',
                'data'    => [
                    'paiement_id'  => $existing->id,
                    'bill_id'      => $existing->semoa_bill_id,
                    'montant'      => $montant,
                    'statut'       => 'initie',
                    'operateur'    => $operateur,
                    'instructions' => $instructionsExisting,
                    'payment_url'  => $existing->semoa_bill_id
                        ? 'https://sandbox.cashpay.tg/facture/' . $existing->semoa_bill_id
                        : null,
                ],
            ]);
        }

        DB::beginTransaction();
        try {
            // ── Créer le bien en statut brouillon + frais en attente ──────────
            $bienController = app(BienController::class);

            // On crée d'abord un paiement "orphelin" pour obtenir son ID
            $paiement = Paiement::create([
                'type_paiement'         => 'frais_etude',
                'payable_type'          => Bien::class,
                'payable_id'            => '00000000-0000-0000-0000-000000000000', // provisoire
                'montant'               => $montant,
                'operateur_paiement'    => $operateur,
                'reference_transaction' => 'FE-' . strtoupper(substr(uniqid(), -8)),
                'statut'                => 'initie',
            ]);

            // Créer le bien en brouillon avec frais en attente
            $bien = $this->creerBienBrouillon($request, $user, $paiement->id);

            // Mettre à jour le paiement avec le vrai payable_id
            $paiement->update([
                'payable_id'            => $bien->id,
                'reference_transaction' => 'FE-' . strtoupper(substr($bien->id, 0, 8)),
            ]);

            // ── Appeler Semoa ─────────────────────────────────────────────────
            $semoa       = app(SemoaService::class);
            $callbackUrl = url('/api/webhooks/semoa?paiement_id=' . $paiement->id);

            $result = $semoa->createOrder([
                'montant'      => $montant,
                'telephone'    => $telephone,
                'operateur'    => $operateur,
                'reference'    => $paiement->reference_transaction . '-' . $paiement->id,
                'description'  => "Frais d'étude ImmoPro — {$categorie->nom} ({$montant} FCFA)",
                'callback_url' => $callbackUrl,
                'redirect_url' => 'immopro://frais-etude/retour?statut=paye&paiement_id=' . $paiement->id,
            ]);

            // ── Déclenchement PUSH USSD ────────────────────────────────────────
            if (in_array($operateur, ['TMONEY', 'FLOOZ']) && ! empty($telephone) && ! empty($result['order_reference'])) {
                try {
                    $semoa->triggerDirectPay($result['order_reference'], $operateur, $telephone);
                } catch (\Throwable $e) {
                    Log::warning('[FraisEtude] triggerDirectPay échoué (non bloquant): ' . $e->getMessage());
                }
            }

            $paiement->update([
                'reference_transaction' => $result['order_reference'] ?? $paiement->reference_transaction,
                'semoa_bill_id'         => $result['order_reference'] ?? null,
            ]);

            DB::commit();

            $instructions = match ($operateur) {
                'TMONEY' => "Notification PUSH T-Money envoyée. Confirmez le paiement de " . number_format($montant, 0, ',', ' ') . " FCFA.",
                'FLOOZ'  => "Notification PUSH Flooz envoyée. Confirmez le paiement de " . number_format($montant, 0, ',', ' ') . " FCFA.",
                'CARD'   => "Rendez-vous sur le portail de paiement sécurisé.",
                default  => "Suivez les instructions de votre opérateur.",
            };

            return response()->json([
                'success' => true,
                'message' => 'Paiement des frais d\'étude initié.',
                'data'    => [
                    'paiement_id'   => $paiement->id,
                    'bien_id'       => $bien->id,
                    'bill_id'       => $result['order_reference'] ?? null,
                    'montant'       => $montant,
                    'pourcentage'   => (float) $categorie->frais_etude_pourcentage,
                    'categorie'     => $categorie->nom,
                    'operateur'     => $operateur,
                    'statut'        => 'initie',
                    'instructions'  => $instructions,
                    'payment_url'   => $result['bill_url'] ?? null,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[FraisEtude] Erreur initiation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initiation du paiement : ' . (app()->isLocal() ? $e->getMessage() : 'Veuillez réessayer.'),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/client/frais-etude/confirmer
    // Confirme le paiement, passe le bien en_attente, décrémente le quota.
    // ─────────────────────────────────────────────────────────────────────────

    public function confirmer(Request $request): JsonResponse
    {
        $request->validate([
            'paiement_id' => 'required|uuid|exists:paiements,id',
        ]);

        $paiement = Paiement::with('payable')->findOrFail($request->paiement_id);
        $bien     = $paiement->payable;

        if (! ($bien instanceof Bien)) {
            return response()->json(['success' => false, 'message' => 'Paiement invalide.'], 422);
        }

        if ($bien->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        // Déjà confirmé
        if (in_array($paiement->statut, ['confirme', 'succes'])) {
            return response()->json([
                'success' => true,
                'message' => 'Paiement déjà confirmé. Votre dossier est en cours de traitement.',
                'data'    => ['bien_id' => $bien->id, 'statut' => $bien->statut],
            ]);
        }

        if ($paiement->statut === 'echoue') {
            return response()->json(['success' => false, 'message' => 'Ce paiement a échoué.'], 422);
        }

        // ── Vérifier auprès de Semoa ──────────────────────────────────────────
        if (! config('services.semoa.simulate')) {
            try {
                $semoa = app(SemoaService::class);
                $order = $semoa->getOrder($paiement->reference_transaction);
                $state = strtoupper($order['state'] ?? 'PENDING');

                if (in_array($state, ['CANCELLED', 'FAILED', 'EXPIRED', 'ERROR'])) {
                    $paiement->update(['statut' => 'echoue']);
                    return response()->json(['success' => false, 'message' => 'Paiement rejeté par l\'opérateur.'], 422);
                }

                if ($state !== 'PAID') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Paiement en cours. Validez sur votre téléphone puis réessayez.',
                    ]);
                }
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de vérifier le statut. Veuillez réessayer.',
                ], 500);
            }
        }

        DB::beginTransaction();
        try {
            $user = $request->user();

            // 1. Confirmer le paiement
            $paiement->update(['statut' => 'confirme']);

            // 2. Activer le bien (passer de brouillon à en_attente)
            $bien->update([
                'statut'              => 'en_attente',
                'frais_etude_statut'  => 'paye',
            ]);

            // 3. Décrémenter le quota
            $abonnement = $user->abonnementActif();
            if ($abonnement) {
                $abonnement->consommerUnePublication();
            } elseif ($user->essais_gratuits_restants > 0) {
                $user->decrement('essais_gratuits_restants');
            }

            // 4. Générer le reçu
            $recu = Recu::create([
                'paiement_id'   => $paiement->id,
                'numero_recu'   => Recu::genererNumero(),
                'date_emission' => now(),
            ]);

            // 5. Notifier admins/agents
            try {
                $notif  = app(\App\Services\NotificationService::class);
                $staffs = \App\Models\User::whereIn('role', ['admin', 'agent'])->get();
                foreach ($staffs as $staff) {
                    $notif->notify(
                        $staff,
                        'nouveau_bien',
                        'Nouveau bien à vérifier',
                        "Le dossier \"{$bien->titre}\" est en attente de vérification après paiement des frais d'étude.",
                        ['bien_id' => (string) $bien->id]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[FraisEtude] Notification staff échouée: ' . $e->getMessage());
            }

            // 6. Notifier le client (push + email)
            try {
                $emailBody = \App\Services\EmailTemplateService::generic(
                    titre: 'Frais d\'étude confirmés',
                    intro: "Le paiement de vos frais d'étude a été confirmé avec succès. Votre dossier est maintenant en cours d'analyse par notre équipe.",
                    rows: [
                        ['icon' => 'home',   'label' => 'Bien',        'value' => $bien->titre],
                        ['icon' => 'money',  'label' => 'Frais payés', 'value' => number_format((float) $paiement->montant, 0, ',', ' ') . ' FCFA'],
                        ['icon' => 'doc',    'label' => 'N° Reçu',     'value' => $recu->numero_recu],
                        ['icon' => 'status', 'label' => 'Statut',      'value' => 'En cours d\'analyse'],
                    ],
                    outro: 'Délai de vérification estimé : 24 à 48 heures. Vous serez notifié dès qu\'une décision sera prise.'
                );

                app(\App\Services\NotificationService::class)->notify(
                    $user,
                    'frais_etude_confirme',
                    'Frais d\'étude confirmés — Dossier en cours d\'analyse',
                    "Le paiement de vos frais d'étude pour \"{$bien->titre}\" a été confirmé. Votre dossier est maintenant en cours d'analyse.",
                    ['bien_id' => (string) $bien->id, 'recu_id' => (string) $recu->id],
                    'Confirmation paiement frais d\'étude — ImmoPro',
                    $emailBody,
                );
            } catch (\Throwable $e) {
                Log::warning('[FraisEtude] Notification client échouée: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Frais d\'étude confirmés ! Votre dossier est maintenant en cours d\'analyse.',
                'data'    => [
                    'bien_id'  => $bien->id,
                    'titre'    => $bien->titre,
                    'statut'   => 'en_attente',
                    'recu'     => [
                        'id'          => $recu->id,
                        'numero_recu' => $recu->numero_recu,
                        'date'        => $recu->date_emission->toIso8601String(),
                        'montant'     => (float) $paiement->montant,
                    ],
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[FraisEtude] Erreur confirmation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/client/frais-etude/historique
    // Liste tous les paiements de frais d'étude de l'utilisateur connecté.
    // ─────────────────────────────────────────────────────────────────────────

    public function historique(Request $request): JsonResponse
    {
        $paiements = Paiement::with(['payable', 'recu'])
            ->where('type_paiement', 'frais_etude')
            ->whereHasMorph('payable', [Bien::class], fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest()
            ->paginate($request->query('per_page', 15));

        $data = $paiements->getCollection()->map(function ($p) {
            $bien = $p->payable;
            return [
                'id'              => $p->id,
                'montant'         => (float) $p->montant,
                'operateur'       => $p->operateur_paiement,
                'statut'          => $p->statut,
                'reference'       => $p->reference_transaction,
                'created_at'      => $p->created_at->toIso8601String(),
                'bien'            => $bien ? [
                    'id'        => $bien->id,
                    'titre'     => $bien->titre,
                    'type_bien' => $bien->type_bien,
                    'statut'    => $bien->statut,
                ] : null,
                'recu'            => $p->recu ? [
                    'id'          => $p->recu->id,
                    'numero_recu' => $p->recu->numero_recu,
                    'date'        => $p->recu->date_emission->toIso8601String(),
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'total'        => $paiements->total(),
                'per_page'     => $paiements->perPage(),
                'current_page' => $paiements->currentPage(),
                'last_page'    => $paiements->lastPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/client/frais-etude/quota
    // Retourne le quota + le montant de frais estimé pour une catégorie
    // ─────────────────────────────────────────────────────────────────────────

    public function quotaEtFrais(Request $request): JsonResponse
    {
        $request->validate([
            'type_bien' => 'required|string|exists:categories,slug',
            'prix'      => 'required|numeric|min:0',
        ]);

        $user      = $request->user();
        $categorie = Categorie::where('slug', $request->type_bien)->first();
        $config    = ConfigPublication::instance();

        $frais = $config->frais_etude_actifs && $categorie
            ? $categorie->calculerFraisEtude((float) $request->prix)
            : 0;

        $abonnement = $user->abonnementActif();

        return response()->json([
            'success' => true,
            'data'    => [
                'peut_publier'              => $user->peutPublier(),
                'essais_gratuits_restants'  => $user->essais_gratuits_restants,
                'abonnement_actif'          => $abonnement ? [
                    'plan'                      => $abonnement->plan->nom,
                    'nb_publications_restantes' => $abonnement->nb_publications_restantes,
                ] : null,
                'frais_etude_actifs'        => $config->frais_etude_actifs,
                'frais_etude_montant'       => $frais,
                'frais_etude_pourcentage'   => $categorie ? (float) $categorie->frais_etude_pourcentage : 0,
                'categorie'                 => $categorie ? $categorie->nom : null,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crée le bien en mode "brouillon" avec frais en attente de paiement.
     * Les médias et documents sont uploadés et sauvegardés immédiatement.
     */
    private function creerBienBrouillon(Request $request, $user, string $paiementId): Bien
    {
        $typeBien  = $request->input('type_bien');
        $categorie = Categorie::where('slug', $typeBien)->first();

        $bien = Bien::create([
            'user_id'                  => $user->id,
            'type_bien'                => $typeBien,
            'type_transaction'         => $request->input('type_transaction'),
            'titre'                    => $request->input('titre'),
            'description'              => $request->input('description'),
            'prix'                     => $request->input('prix'),
            'prix_public'              => $categorie
                                            ? $categorie->calculerPrixPublic((float) $request->input('prix'))
                                            : $request->input('prix'),
            'unite_prix'               => $request->input('unite_prix'),
            'avance_mois'              => $request->input('avance_mois'),
            'caution'                  => $request->input('caution'),
            'surface'                  => $request->input('surface'),
            'superficie'               => $request->input('superficie'),
            'nb_pieces'                => $request->input('nb_pieces'),
            'nb_salles_bain'           => $request->input('nb_salles_bain'),
            'caracteristiques'         => $request->input('caracteristiques'),
            'adresse'                  => $request->input('adresse'),
            'latitude'                 => $request->input('latitude'),
            'longitude'                => $request->input('longitude'),
            'statut'                   => 'brouillon',
            'role_deposant'            => $request->input('role_deposant', 'proprietaire'),
            'proprietaire_nom'         => $request->input('proprietaire_nom'),
            'proprietaire_prenom'      => $request->input('proprietaire_prenom'),
            'proprietaire_sexe'        => $request->input('proprietaire_sexe'),
            'proprietaire_nationalite' => $request->input('proprietaire_nationalite'),
            'proprietaire_telephone'   => $request->input('proprietaire_telephone'),
            'proprietaire_email'       => $request->input('proprietaire_email'),
            'proprietaire_adresse'     => $request->input('proprietaire_adresse'),
            'frais_etude_statut'       => 'en_attente_paiement',
            'frais_etude_paiement_id'  => $paiementId,
        ]);

        // Sauvegarder médias
        if ($request->hasFile('medias')) {
            foreach ($request->file('medias') as $index => $fichier) {
                $mime    = $fichier->getMimeType();
                $isVideo = str_starts_with($mime, 'video/');
                $dossier = "biens/{$bien->id}/medias";
                $chemin  = $fichier->store($dossier, 'public');

                \App\Models\MediaBien::create([
                    'bien_id'        => $bien->id,
                    'type'           => $isVideo ? 'video' : 'photo',
                    'chemin'         => $chemin,
                    'est_principale' => $index === 0,
                    'ordre'          => $index,
                    'taille'         => $fichier->getSize(),
                    'mime_type'      => $mime,
                ]);
            }
        }

        // Sauvegarder documents — dynamiques depuis la config
        $docsConfig = \App\Models\ConfigDocParRole::with('typeDocument')
            ->whereHas('role', fn ($q) => $q->where('slug', $request->input('role_deposant', 'proprietaire')))
            ->get();

        $slugsValides = $docsConfig
            ->filter(fn ($dr) => $dr->typeDocument && $dr->typeDocument->actif)
            ->pluck('typeDocument.slug')
            ->toArray();

        // Docs optionnels de la catégorie
        if ($categorie && ! empty($categorie->documents_optionnels)) {
            $slugsValides = array_unique(array_merge($slugsValides, $categorie->documents_optionnels));
        }

        foreach ($slugsValides as $slug) {
            if ($request->hasFile("documents.{$slug}")) {
                $fichier = $request->file("documents.{$slug}");
                $dossier = "biens/{$bien->id}/documents";
                $chemin  = $fichier->store($dossier, 'local');

                \App\Models\DocumentBien::create([
                    'bien_id'      => $bien->id,
                    'type'         => $slug,
                    'chemin'       => $chemin,
                    'nom_original' => $fichier->getClientOriginalName(),
                    'taille'       => $fichier->getSize(),
                    'mime_type'    => $fichier->getMimeType(),
                    'statut'       => 'en_attente',
                ]);
            }
        }

        return $bien;
    }
}
