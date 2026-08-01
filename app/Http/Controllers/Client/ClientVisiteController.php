<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\CreneauVisite;
use App\Models\Paiement;use App\Models\Recu;
use App\Models\User;
use App\Models\Visite;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;
use App\Services\Payment\SemoaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientVisiteController extends Controller
{
    public function __construct(private readonly NotificationService $notifService) {}

    // ── GET /api/client/visites ────────────────────────────────────────────
    // Visites de vérification liées aux biens du propriétaire connecté.
    // Inclut les créneaux proposés par l'agent pour que le proprio puisse en choisir un.
    public function index(Request $request): JsonResponse
    {
        $visites = Visite::with(['bien', 'agent'])
            ->whereHas('bien', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('type_visite', Visite::TYPE_VERIFICATION)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($v) => [
                'id'            => $v->id,
                'date_visite'   => $v->date_visite?->toIso8601String(),
                'duree_minutes' => $v->duree_minutes,
                'statut'        => $v->statut,
                'proprio_note'  => $v->proprio_note,
                'notes'         => $v->notes,
                'bien_titre'    => $v->bien?->titre,
                'bien_id'       => $v->bien_id,
                // Créneaux proposés par l'agent (le proprio doit en choisir un)
                'creneaux'      => collect($v->creneaux_agent ?? [])->values()->toArray(),
                'agent_nom'     => $v->agent
                    ? trim("{$v->agent->first_name} {$v->agent->last_name}")
                    : null,
            ]);

        return response()->json(['success' => true, 'data' => $visites]);
    }

    // ── GET /api/client/biens/{bienId}/creneaux ────────────────────────────

    // ── POST /api/client/visites/{visiteId}/choisir-creneau-verification ──────
    // Le propriétaire choisit un créneau de visite de vérification proposé par l'agent.
    // Gère les deux sources : creneaux_agent (JSON) et table creneaux_visite.
    // ─────────────────────────────────────────────────────────────────────────
    public function choisirCreneauVerification(Request $request, string $visiteId): JsonResponse
    {
        $proprio = $request->user();

        $request->validate([
            'index_creneau' => 'required|integer|min:0',
        ]);

        $visite = Visite::where('id', $visiteId)
            ->where('type_visite', Visite::TYPE_VERIFICATION)
            // Accepter proposee ET en_attente_client (les créneaux table ne changent pas le statut)
            ->whereIn('statut', [Visite::STATUT_PROPOSEE, Visite::STATUT_EN_ATTENTE_CLIENT])
            ->whereHas('bien', fn ($q) => $q->where('user_id', $proprio->id))
            ->with(['bien.agent'])
            ->firstOrFail();

        $idx = (int) $request->input('index_creneau');

        // ── Chercher le créneau selon la source ───────────────────────────────
        $dateVisite = null;
        $duree      = 60;

        // Source 1 : creneaux_agent (JSON sur visite)
        $creneauxJson = $visite->creneaux_agent ?? [];
        if (!empty($creneauxJson) && isset($creneauxJson[$idx])) {
            $c          = $creneauxJson[$idx];
            $dateVisite = \Carbon\Carbon::parse($c['date_debut']);
            $duree      = (int) ($c['duree_minutes'] ?? 60);
        }

        // Source 2 : table creneaux_visite (si creneaux_agent vide)
        if ($dateVisite === null) {
            $creneauxTable = CreneauVisite::where('bien_id', $visite->bien_id)
                ->where('statut', 'disponible')
                ->orderBy('date_debut')
                ->get();

            if ($creneauxTable->isEmpty() || !isset($creneauxTable[$idx])) {
                $total = max(count($creneauxJson), $creneauxTable->count());
                return response()->json([
                    'success' => false,
                    'message' => "Index de créneau invalide. {$total} créneau(x) disponible(s) (index 0 à " . ($total - 1) . ').',
                ], 422);
            }

            $cv         = $creneauxTable[$idx];
            $dateVisite = $cv->date_debut;
            $duree      = $cv->date_debut && $cv->date_fin
                ? (int) $cv->date_debut->diffInMinutes($cv->date_fin)
                : 60;

            // Marquer le créneau choisi et expirer les autres
            $cv->update(['statut' => 'choisi', 'visite_id' => $visite->id]);
            CreneauVisite::where('bien_id', $visite->bien_id)
                ->where('statut', 'disponible')
                ->where('id', '!=', $cv->id)
                ->update(['statut' => 'expire']);
        }

        $visite->update([
            'date_visite'             => $dateVisite,
            'duree_minutes'           => $duree,
            'statut'                  => Visite::STATUT_CONFIRMEE,
            'confirme_par_proprio_le' => now(),
        ]);

        $bien      = $visite->bien;
        $dateLabel = $dateVisite->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm');

        // Notifier l'agent
        if ($bien?->agent) {
            $html = \App\Services\EmailTemplateService::generic(
                titre: '✅ Créneau de vérification confirmé !',
                intro: "Le propriétaire a choisi le créneau du {$dateLabel} pour la visite de vérification de « {$bien->titre} ».",
                rows: [
                    ['icon' => '🏠', 'label' => 'Bien',    'value' => $bien->titre],
                    ['icon' => '📍', 'label' => 'Adresse', 'value' => $bien->adresse ?? '—'],
                    ['icon' => '📅', 'label' => 'Date',    'value' => $dateLabel],
                    ['icon' => '⏱️', 'label' => 'Durée',   'value' => "{$duree} min"],
                ],
                outro: 'Préparez-vous pour la visite de vérification.'
            );
            $this->notifService->notify(
                $bien->agent,
                'creneau_verification_confirme',
                '✅ Créneau de vérification confirmé',
                "Le propriétaire a confirmé le créneau du {$dateLabel} pour « {$bien->titre} ».",
                ['visite_id' => $visite->id, 'bien_id' => $bien->id, 'date_visite' => $dateVisite->toIso8601String()],
                "ImmoPro — Vérification confirmée : {$bien->titre}",
                $html
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Créneau confirmé. Votre visite de vérification est planifiée pour le {$dateLabel}.",
            'data'    => [
                'visite_id'    => $visite->id,
                'statut'       => $visite->statut,
                'date_visite'  => $dateVisite->toIso8601String(),
                'duree_minutes'=> $duree,
            ],
        ]);
    }

    public function getCreneaux(Request $request, string $bienId): JsonResponse
    {
        // Vérifier que le bien appartient au propriétaire
        $bien = Bien::where('id', $bienId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $creneaux = CreneauVisite::where('bien_id', $bienId)
            ->where('statut', 'disponible')
            ->orderBy('date_debut')
            ->get()
            ->map(fn ($c) => [
                'id'         => $c->id,
                'date_debut' => $c->date_debut?->toIso8601String(),
                'date_fin'   => $c->date_fin?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $creneaux]);
    }

    // ── POST /api/client/biens/{bienId}/creneaux/{creneauId}/choisir ───────
    public function choisirCreneau(Request $request, string $bienId, string $creneauId): JsonResponse
    {
        $proprio = $request->user();

        $bien = Bien::where('id', $bienId)
            ->where('user_id', $proprio->id)
            ->with(['agent'])
            ->firstOrFail();

        $creneau = CreneauVisite::where('id', $creneauId)
            ->where('bien_id', $bienId)
            ->where('statut', 'disponible')
            ->firstOrFail();

        // Créer la visite confirmée
        $visite = Visite::create([
            'bien_id'                 => $bienId,
            'agent_id'                => $creneau->agent_id,
            'date_visite'             => $creneau->date_debut,
            'duree_minutes'           => $creneau->date_debut->diffInMinutes($creneau->date_fin),
            'type_visite'             => Visite::TYPE_VERIFICATION,
            'statut'                  => 'confirmee',
            'confirme_par_proprio_le' => now(),
        ]);

        // Marquer le créneau choisi
        $creneau->update(['statut' => 'choisi', 'visite_id' => $visite->id]);

        // Expirer les autres créneaux disponibles pour ce bien
        CreneauVisite::where('bien_id', $bienId)
            ->where('statut', 'disponible')
            ->where('id', '!=', $creneauId)
            ->update(['statut' => 'expire']);

        $dateLabel = \Carbon\Carbon::parse($visite->date_visite)->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm');

        // Notifier l'agent
        if ($bien->agent) {
            $html = EmailTemplateService::generic(
                titre: '✅ Créneau confirmé par le propriétaire',
                intro: "Le propriétaire a choisi le créneau du {$dateLabel} pour la visite de « {$bien->titre} ».",
                rows:  [
                    ['icon' => '🏠', 'label' => 'Bien',  'value' => $bien->titre],
                    ['icon' => '📅', 'label' => 'Date',  'value' => $dateLabel],
                ],
            );
            $this->notifService->notify($bien->agent, 'visite_confirmee_proprio',
                'Visite confirmée',
                "Le propriétaire a choisi le créneau du {$dateLabel} pour « {$bien->titre} ».",
                ['visite_id' => $visite->id, 'bien_id' => $bienId],
                "ImmoPro — Visite confirmée : {$bien->titre}", $html);
        }

        // Notifier les admins (in-app uniquement)
        foreach (User::where('role', 'admin')->get() as $admin) {
            $this->notifService->notify($admin, 'visite_confirmee_admin',
                'Visite confirmée',
                "Visite confirmée pour « {$bien->titre} » le {$dateLabel}.",
                ['visite_id' => $visite->id, 'bien_id' => $bienId]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Créneau choisi. La visite est confirmée.',
            'visite'  => ['id' => $visite->id, 'date_visite' => $visite->date_visite?->toIso8601String(), 'statut' => $visite->statut],
        ], 201);
    }

    // ── POST /api/client/visites/{id}/annuler ──────────────────────────────
    public function annulerVisite(Request $request, string $visiteId): JsonResponse
    {
        $proprio = $request->user();
        $request->validate(['note' => 'nullable|string|max:500']);

        $visite = Visite::whereHas('bien', fn ($q) => $q->where('user_id', $proprio->id))
            ->where('id', $visiteId)
            ->where('statut', 'confirmee')
            ->with(['bien.agent'])
            ->firstOrFail();

        $visite->update(['statut' => 'annulee', 'proprio_note' => $request->note]);

        $bien      = $visite->bien;
        $dateLabel = \Carbon\Carbon::parse($visite->date_visite)->locale('fr')->isoFormat('D MMM YYYY [à] HH[h]mm');

        // Notifier l'agent
        if ($bien?->agent) {
            $msg  = "❌ Le propriétaire a annulé la visite du {$dateLabel} pour « {$bien->titre} »."
                . ($request->note ? " Note : {$request->note}" : '');
            $html = EmailTemplateService::generic(
                titre:   '❌ Visite annulée',
                intro:   $msg,
                rows:    [
                    ['icon' => '🏠', 'label' => 'Bien', 'value' => $bien->titre],
                    ['icon' => '📅', 'label' => 'Date annulée', 'value' => $dateLabel],
                ],
                noteBox: $request->note ?: null,
                outro:   'Vous pouvez proposer de nouveaux créneaux au propriétaire.'
            );
            $this->notifService->notify($bien->agent, 'visite_annulee_proprio',
                'Visite annulée', $msg,
                ['visite_id' => $visite->id, 'bien_id' => $bien->id],
                "ImmoPro — Visite annulée : {$bien->titre}", $html);
        }

        return response()->json(['success' => true, 'message' => 'Visite annulée.']);
    }

    // ── POST /api/client/visites/biens/{bienId}/initier ───────────────────────
    // Initie le paiement des frais de visite via Semoa CashPay.
    // Après succès, le client aura accès à la localisation exacte du bien.
    // ─────────────────────────────────────────────────────────────────────────
    public function initierPaiement(Request $request, string $bienId): JsonResponse
    {
        $request->validate([
            'operateur_paiement' => 'required|string|in:TMONEY,FLOOZ,CARD,tmoney,flooz,card',
            'telephone'          => [
                'required_if:operateur_paiement,TMONEY,FLOOZ,tmoney,flooz',
                'nullable', 'string',
            ],
        ]);

        $client = $request->user();

        $bien = Bien::with(['categorie'])->where('statut', 'publie')->findOrFail($bienId);

        // Le propriétaire ne peut pas payer pour visiter son propre bien
        if ($bien->user_id === $client->id) {
            return response()->json(['success' => false, 'message' => 'Vous ne pouvez pas demander une visite de votre propre bien.'], 403);
        }

        // Calculer le prix de visite :
        // 1. Utiliser le prix_visite du bien s'il est défini
        // 2. Sinon calculer depuis la configuration de la catégorie (fallback)
        $prixVisite = (float) ($bien->prix_visite ?? 0);
        if ($prixVisite <= 0 && $bien->categorie) {
            $cat = $bien->categorie;
            if ($cat->visite_tarif_type === 'pourcentage' && $cat->visite_pourcentage > 0) {
                $prixVisite = $cat->calculerPrixVisite((float) $bien->prix);
            } elseif ($cat->visite_tarif_type === 'fixe_manuel' && $cat->visite_tarif_fixe > 0) {
                $prixVisite = (float) $cat->visite_tarif_fixe;
            }
            // Si on a trouvé un tarif via la catégorie, le persister sur le bien pour les prochains appels
            if ($prixVisite > 0) {
                $bien->update(['prix_visite' => $prixVisite]);
            }
        }

        if ($prixVisite <= 0) {
            return response()->json(['success' => false, 'message' => 'Ce bien n\'a pas encore de tarif de visite configuré. Contactez l\'administration.'], 422);
        }

        // Idempotence : éviter un double déclenchement si déjà payé
        if ($bien->hasPaidVisit($client)) {
            return response()->json([
                'success' => true,
                'message' => 'Vous avez déjà accès à la localisation de ce bien.',
                'data'    => ['visite_payee' => true, 'montant' => $prixVisite],
            ]);
        }

        // Vérifier un paiement en cours (initié il y a moins de 20 min)
        $existing = Paiement::where('type_paiement', 'visite')
            ->where('statut', 'initie')
            ->whereHasMorph('payable', [Bien::class], fn ($q) => $q->where('id', $bienId))
            ->where('created_at', '>=', now()->subMinutes(20))
            ->latest()->first();

        $operateur = strtoupper($request->input('operateur_paiement'));
        $telephone = $request->input('telephone', '');
        if ($telephone && !str_starts_with($telephone, '+')) {
            $telephone = str_starts_with($telephone, '228') ? '+' . $telephone : '+228' . $telephone;
        }

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Un paiement est déjà en cours.',
                'data'    => [
                    'paiement_id' => $existing->id,
                    'bill_id'     => $existing->semoa_bill_id,
                    'montant'     => $prixVisite,
                    'statut'      => 'initie',
                    'payment_url' => $existing->semoa_bill_id
                        ? 'https://sandbox.cashpay.tg/facture/' . $existing->semoa_bill_id
                        : null,
                ],
            ]);
        }

        DB::beginTransaction();
        try {
            $reference = 'VIS-' . strtoupper(substr(uniqid(), -8));

            $paiement = Paiement::create([
                'type_paiement'         => 'visite',
                'payable_type'          => Bien::class,
                'payable_id'            => $bienId,
                'location_id'           => null,
                'montant'               => $prixVisite,
                'operateur_paiement'    => $operateur,
                'reference_transaction' => $reference,
                'statut'                => 'initie',
            ]);

            $semoa = app(SemoaService::class);
            $callbackUrl = url('/api/webhooks/semoa?paiement_id=' . $paiement->id);

            $result = $semoa->createOrder([
                'montant'      => $prixVisite,
                'telephone'    => $telephone,
                'operateur'    => $operateur,
                'reference'    => $reference . '-' . $paiement->id,
                'description'  => "Frais de visite ImmoPro — {$bien->titre}",
                'callback_url' => $callbackUrl,
                'redirect_url' => "immopro://paiement/retour?statut=paye&paiement_id={$paiement->id}",
            ]);

            if (in_array($operateur, ['TMONEY', 'FLOOZ']) && !empty($telephone) && !empty($result['order_reference'])) {
                try {
                    $semoa->triggerDirectPay($result['order_reference'], $operateur, $telephone);
                } catch (\Throwable $e) {
                    Log::warning('[VisitePaiement] triggerDirectPay échoué (non bloquant): ' . $e->getMessage());
                }
            }

            $paiement->update([
                'reference_transaction' => $result['order_reference'] ?? $reference,
                'semoa_bill_id'         => $result['order_reference'] ?? null,
            ]);

            DB::commit();

            $instructions = match ($operateur) {
                'TMONEY' => 'Notification PUSH T-Money envoyée. Confirmez le paiement de ' . number_format($prixVisite, 0, ',', ' ') . ' FCFA.',
                'FLOOZ'  => 'Notification PUSH Flooz envoyée. Confirmez le paiement de ' . number_format($prixVisite, 0, ',', ' ') . ' FCFA.',
                'CARD'   => 'Rendez-vous sur le portail de paiement sécurisé.',
                default  => 'Suivez les instructions de votre opérateur.',
            };

            return response()->json([
                'success' => true,
                'message' => 'Paiement des frais de visite initié.',
                'data'    => [
                    'paiement_id'  => $paiement->id,
                    'bien_id'      => $bienId,
                    'bill_id'      => $result['order_reference'] ?? null,
                    'montant'      => $prixVisite,
                    'operateur'    => $operateur,
                    'statut'       => 'initie',
                    'instructions' => $instructions,
                    'payment_url'  => $result['bill_url'] ?? null,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[VisitePaiement] Erreur initiation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initiation du paiement : ' . (app()->isLocal() ? $e->getMessage() : 'Veuillez réessayer.'),
            ], 500);
        }
    }

    // ── POST /api/client/visites/biens/{bienId}/confirmer ────────────────────
    // Confirme le paiement Semoa (polling client), crée la visite payée, génère le reçu.
    // Même pattern que AbonnementController::confirmer() :
    //   - vérification via getOrder() + state === 'PAID'
    //   - statut paiement : 'confirme'
    //   - reçu via Recu::genererNumero()
    //   - notification email via EmailTemplateService
    // ─────────────────────────────────────────────────────────────────────────
    public function confirmerPaiement(Request $request, string $bienId): JsonResponse
    {
        $request->validate([
            'paiement_id' => 'required|uuid|exists:paiements,id',
        ]);

        $client   = $request->user();
        $paiement = Paiement::with('payable')->findOrFail($request->paiement_id);

        // Déjà confirmé (idempotence)
        if (in_array($paiement->statut, ['confirme', 'succes'])) {
            return response()->json([
                'success' => true,
                'message' => 'Paiement déjà confirmé.',
                'data'    => ['visite_payee' => true],
            ]);
        }

        // Échoué
        if ($paiement->statut === 'echoue') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement a échoué. Veuillez réessayer.',
            ], 422);
        }

        // ── Vérifier auprès de Semoa (même logique que AbonnementController) ─
        if (!config('services.semoa.simulate')) {
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
                        'message' => 'Paiement toujours en cours. Validez sur votre téléphone puis réessayez.',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('[VisitePaiement] Erreur vérification Semoa: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de vérifier le statut. Veuillez réessayer.',
                ], 500);
            }
        }

        DB::beginTransaction();
        try {
            $bien = Bien::findOrFail($bienId);

            // 1. Confirmer le paiement (même statut que abonnement)
            $paiement->update(['statut' => 'confirme']);

            // 2. Créer la visite payée ou marquer l'existante
            $visite = Visite::where('bien_id', $bienId)
                ->where('client_id', $client->id)
                ->where('type_visite', Visite::TYPE_CLIENT)
                ->latest()->first();

            if ($visite) {
                $visite->update(['est_payee' => true, 'statut' => 'proposee']);
            } else {
                $visite = Visite::create([
                    'bien_id'    => $bienId,
                    'agent_id'   => $bien->agent_id,
                    'client_id'  => $client->id,
                    'type_visite'=> Visite::TYPE_CLIENT,
                    'statut'     => 'proposee',
                    'est_payee'  => true,
                    'notes'      => 'Visite payée le ' . now()->toDateString() . ' — Planification à définir.',
                ]);
            }

            // 3. Générer le reçu (même helper Recu::genererNumero() que abonnement/frais_etude)
            $recu = Recu::firstOrCreate(
                ['paiement_id' => $paiement->id],
                ['numero_recu' => Recu::genererNumero(), 'date_emission' => now()]
            );

            // 4. Notifier le client (avec email template — même pattern que abonnement)
            try {
                $operateurLabel = match(strtoupper($paiement->operateur_paiement ?? '')) {
                    'CARD'   => 'Carte bancaire',
                    'TMONEY' => 'T-Money',
                    'FLOOZ'  => 'Moov Flooz',
                    default  => $paiement->operateur_paiement ?? '—',
                };
                $this->notifService->notify(
                    $client,
                    'visite_payee',
                    '✅ Paiement de visite confirmé !',
                    "Votre paiement pour visiter « {$bien->titre} » a été confirmé. Vous pouvez maintenant voir la localisation exacte.",
                    ['visite_id' => $visite->id, 'bien_id' => $bienId],
                    '✅ Visite confirmée — ImmoPro',
                    \App\Services\EmailTemplateService::generic(
                        'Frais de visite confirmés !',
                        "Votre paiement pour visiter <strong>{$bien->titre}</strong> a été reçu et confirmé. La localisation exacte est maintenant déverrouillée.",
                        [
                            ['icon' => '🏠', 'label' => 'Bien',           'value' => $bien->titre],
                            ['icon' => '💰', 'label' => 'Montant payé',   'value' => number_format((float) $paiement->montant, 0, ',', ' ') . ' FCFA'],
                            ['icon' => '💳', 'label' => 'Opérateur',      'value' => $operateurLabel],
                            ['icon' => '🧾', 'label' => 'Reçu',           'value' => $recu->numero_recu],
                        ],
                        null,
                        'Connectez-vous à l\'application pour voir la localisation et planifier votre visite.'
                    )
                );
            } catch (\Throwable $e) {
                Log::warning('[VisitePaiement] Notification client échouée : ' . $e->getMessage());
            }

            // 5. Notifier l'agent assigné
            if ($bien->agent_id) {
                try {
                    $agent = \App\Models\User::find($bien->agent_id);
                    if ($agent) {
                        $this->notifService->notify(
                            $agent, 'visite_client_payee',
                            'Visite client payée',
                            "Un client a payé les frais de visite pour « {$bien->titre} ». Planifiez la visite.",
                            ['visite_id' => $visite->id, 'bien_id' => $bienId]
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning('[VisitePaiement] Notification agent échouée : ' . $e->getMessage());
                }
            }

            DB::commit();

            Log::info('[VisitePaiement] Confirmation réussie', [
                'paiement_id' => $paiement->id,
                'visite_id'   => $visite->id,
                'bien_id'     => $bienId,
                'recu'        => $recu->numero_recu,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paiement confirmé. Vous avez maintenant accès à la localisation du bien.',
                'data'    => [
                    'visite_id'    => $visite->id,
                    'visite_payee' => true,
                    'recu'         => [
                        'id'          => $recu->id,
                        'numero_recu' => $recu->numero_recu,
                        'date'        => $recu->date_emission->toIso8601String(),
                    ],
                    'bien_id'      => $bienId,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[VisitePaiement] Erreur confirmation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }


    // ── POST /api/client/visites/{visiteId}/choisir-creneau ─────────────────
    // Le client choisit l'un des créneaux proposés par l'agent.
    // Prérequis : visite de type 'client', statut 'en_attente_client'.
    // ─────────────────────────────────────────────────────────────────────────
    public function choisirCreneauVisite(Request $request, string $visiteId): JsonResponse
    {
        $client = $request->user();

        $request->validate([
            'index_creneau' => 'required|integer|min:0',
            'note'          => 'nullable|string|max:500',
        ]);

        $visite = Visite::where('id', $visiteId)
            ->where('client_id', $client->id)
            ->where('type_visite', Visite::TYPE_CLIENT)
            ->where('statut', Visite::STATUT_EN_ATTENTE_CLIENT)
            ->with(['bien.agent'])
            ->firstOrFail();

        $creneaux = $visite->creneaux_agent ?? [];
        $idx      = (int) $request->input('index_creneau');

        if (! isset($creneaux[$idx])) {
            return response()->json([
                'success' => false,
                'message' => 'Index de créneau invalide. L\'agent a proposé ' . count($creneaux) . ' créneau(x) (index 0 à ' . (count($creneaux) - 1) . ').',
            ], 422);
        }

        $creneau    = $creneaux[$idx];
        $dateVisite = \Carbon\Carbon::parse($creneau['date_debut']);
        $duree      = (int) ($creneau['duree_minutes'] ?? 60);

        $visite->update([
            'date_visite'   => $dateVisite,
            'duree_minutes' => $duree,
            'statut'        => Visite::STATUT_CONFIRMEE,
            'notes'         => $request->input('note') ?: $visite->notes,
        ]);

        $bien      = $visite->bien;
        $nomClient = trim("{$client->first_name} {$client->last_name}");
        $dateLabel = $dateVisite->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm');

        // Notifier l'agent
        if ($bien?->agent) {
            $html = EmailTemplateService::generic(
                titre: '✅ Créneau choisi par le client !',
                intro: "Le client {$nomClient} a choisi le créneau du {$dateLabel} pour visiter « {$bien->titre} ».",
                rows: [
                    ['icon' => '🏠', 'label' => 'Bien',   'value' => $bien->titre],
                    ['icon' => '📅', 'label' => 'Date',   'value' => $dateLabel],
                    ['icon' => '⏱️', 'label' => 'Durée',  'value' => $duree . ' min'],
                    ['icon' => '👤', 'label' => 'Client', 'value' => $nomClient],
                ],
                outro: 'Préparez la visite et présentez-vous à l\'adresse du bien.'
            );
            $this->notifService->notify(
                $bien->agent,
                'visite_client_confirmee_agent',
                'Visite confirmée par le client',
                "Le client {$nomClient} a confirmé le créneau du {$dateLabel} pour « {$bien->titre} ».",
                ['visite_id' => $visite->id, 'bien_id' => $bien->id, 'date_visite' => $dateVisite->toIso8601String()],
                "ImmoPro — Visite confirmée : {$bien->titre}",
                $html
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Créneau choisi. Votre visite est confirmée pour le {$dateLabel}.",
            'data'    => [
                'visite_id'    => $visite->id,
                'statut'       => $visite->statut,
                'date_visite'  => $dateVisite->toIso8601String(),
                'duree_minutes'=> $duree,
            ],
        ]);
    }

    // ── POST /api/client/visites/{visiteId}/indisponible ─────────────────────
    // Le client signale qu'aucun créneau proposé ne lui convient.
    // L'agent sera notifié (in-app + mail) pour re-proposer de nouveaux créneaux.
    // ─────────────────────────────────────────────────────────────────────────
    public function signalerIndisponibilite(Request $request, string $visiteId): JsonResponse
    {
        $client = $request->user();

        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $visite = Visite::where('id', $visiteId)
            ->where('client_id', $client->id)
            ->where('type_visite', Visite::TYPE_CLIENT)
            ->where('statut', Visite::STATUT_EN_ATTENTE_CLIENT)
            ->with(['bien.agent'])
            ->firstOrFail();

        $visite->update([
            'statut'               => Visite::STATUT_INDISPONIBLE,
            'nb_indisponibilites'  => ($visite->nb_indisponibilites ?? 0) + 1,
            'note_indisponibilite' => $request->input('note'),
            'creneaux_agent'       => [], // vider les anciens créneaux
        ]);

        $bien      = $visite->bien;
        $nomClient = trim("{$client->first_name} {$client->last_name}");
        $nb        = $visite->nb_indisponibilites;

        // Notifier l'agent
        if ($bien?->agent) {
            $noteClient = $request->input('note') ?: 'Aucune note fournie.';

            $html = \App\Services\EmailTemplateService::generic(
                titre: '⚠️ Client indisponible — nouvelle proposition requise',
                intro: "Le client {$nomClient} ne peut pas se rendre aux créneaux proposés pour « {$bien->titre} ». Veuillez proposer de nouveaux créneaux.",
                rows: [
                    ['icon' => '🏠', 'label' => 'Bien',           'value' => $bien->titre],
                    ['icon' => '👤', 'label' => 'Client',         'value' => $nomClient],
                    ['icon' => '🔄', 'label' => 'Tentatives',     'value' => "{$nb} fois"],
                    ['icon' => '📝', 'label' => 'Note du client', 'value' => $noteClient],
                ],
                outro: 'Connectez-vous à votre espace agent pour proposer de nouveaux créneaux.'
            );

            $this->notifService->notify(
                $bien->agent,
                'client_indisponible',
                '⚠️ Client indisponible — reproposez des créneaux',
                "{$nomClient} est indisponible pour les créneaux proposés pour « {$bien->titre} ».",
                [
                    'visite_id'  => $visite->id,
                    'bien_id'    => $bien->id,
                    'client_nom' => $nomClient,
                    'nb_fois'    => $nb,
                ],
                "ImmoPro — Client indisponible : {$bien->titre}",
                $html
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Votre indisponibilité a été signalée. L\'agent va vous proposer de nouveaux créneaux.',
            'data'    => [
                'visite_id'           => $visite->id,
                'statut'              => $visite->statut,
                'nb_indisponibilites' => $nb,
            ],
        ]);
    }

    // ── POST /api/client/visites/{visiteId}/indisponible-verification ─────────
    // Le propriétaire signale qu'aucun créneau de VÉRIFICATION ne lui convient.
    // L'agent sera notifié pour re-proposer de nouveaux créneaux.
    // ─────────────────────────────────────────────────────────────────────────
    public function signalerIndisponibiliteVerification(Request $request, string $visiteId): JsonResponse
    {
        $proprio = $request->user();

        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $visite = Visite::where('id', $visiteId)
            ->where('type_visite', Visite::TYPE_VERIFICATION)
            ->whereIn('statut', [Visite::STATUT_EN_ATTENTE_CLIENT, Visite::STATUT_PROPOSEE])
            ->whereHas('bien', fn ($q) => $q->where('user_id', $proprio->id))
            ->with(['bien.agent'])
            ->firstOrFail();

        $visite->update([
            'statut'               => Visite::STATUT_INDISPONIBLE,
            'nb_indisponibilites'  => ($visite->nb_indisponibilites ?? 0) + 1,
            'note_indisponibilite' => $request->input('note'),
            'creneaux_agent'       => [], // vider les anciens créneaux JSON
        ]);

        // Supprimer aussi les créneaux de la table (flow interface web)
        \App\Models\CreneauVisite::where('bien_id', $visite->bien_id)
            ->where('statut', 'disponible')
            ->delete();

        $bien     = $visite->bien;
        $nomProprio = trim("{$proprio->first_name} {$proprio->last_name}");
        $nb       = $visite->nb_indisponibilites;

        // Notifier l'agent
        if ($bien?->agent) {
            $noteClient = $request->input('note') ?: 'Aucune note fournie.';

            $html = \App\Services\EmailTemplateService::generic(
                titre: '⚠️ Propriétaire indisponible — re-planification requise',
                intro: "Le propriétaire {$nomProprio} ne peut pas se rendre aux créneaux proposés pour la vérification de « {$bien->titre} ». Veuillez proposer de nouveaux créneaux.",
                rows: [
                    ['icon' => '🏠', 'label' => 'Bien',            'value' => $bien->titre],
                    ['icon' => '👤', 'label' => 'Propriétaire',    'value' => $nomProprio],
                    ['icon' => '🔄', 'label' => 'Tentatives',      'value' => "{$nb} fois"],
                    ['icon' => '📝', 'label' => 'Note',            'value' => $noteClient],
                ],
                outro: 'Connectez-vous à votre espace agent pour proposer de nouveaux créneaux de vérification.'
            );

            $this->notifService->notify(
                $bien->agent,
                'proprio_indisponible_verification',
                '⚠️ Propriétaire indisponible — re-planifier la vérification',
                "{$nomProprio} est indisponible pour les créneaux proposés pour « {$bien->titre} ».",
                [
                    'visite_id'  => $visite->id,
                    'bien_id'    => $bien->id,
                    'proprio_nom'=> $nomProprio,
                    'nb_fois'    => $nb,
                ],
                "ImmoPro — Proprio indisponible : {$bien->titre}",
                $html
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Votre indisponibilité a été signalée. L\'agent va vous proposer de nouveaux créneaux.',
            'data'    => [
                'visite_id'           => $visite->id,
                'statut'              => $visite->statut,
                'nb_indisponibilites' => $nb,
            ],
        ]);
    }

    // ── GET /api/client/visites ────────────────────────────────────────────
    // Historique des visites du client (payées et autres)
    // ─────────────────────────────────────────────────────────────────────────
    public function mesVisites(Request $request): JsonResponse    {
        $client = $request->user();

        $visites = Visite::with(['bien.medias'])
            ->where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($v) => [
                'id'               => $v->id,
                'bien_id'          => $v->bien_id,
                'bien_titre'       => $v->bien?->titre,
                'bien_adresse'     => $v->bien?->adresse,
                'bien_photo'       => $v->bien?->medias
                    ?->where('est_principale', true)->first()?->url
                    ?? $v->bien?->medias?->first()?->url,
                'date_visite'      => $v->date_visite?->toIso8601String(),
                'statut'           => $v->statut,
                'est_payee'        => $v->est_payee,
                // Créneaux proposés par l'agent (le client doit en choisir un)
                'creneaux_agent'      => $v->creneaux_agent,
                'duree_minutes'       => $v->duree_minutes,
                'nb_indisponibilites' => $v->nb_indisponibilites ?? 0,
                'created_at'          => $v->created_at->toIso8601String(),            ]);

        return response()->json(['success' => true, 'data' => $visites]);
    }
}
