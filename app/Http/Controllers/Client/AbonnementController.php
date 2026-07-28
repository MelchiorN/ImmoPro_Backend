<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ConfigPublication;
use App\Models\Paiement;
use App\Models\PlanAbonnement;
use App\Models\Recu;
use App\Models\UserAbonnement;
use App\Services\Payment\SemoaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AbonnementController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/client/abonnements/plans
    // Liste des plans actifs disponibles à l'achat
    // ─────────────────────────────────────────────────────────────────────────

    public function plans(): JsonResponse
    {
        $plans = PlanAbonnement::actif()->get();

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/client/abonnements/quota
    // Retourne le quota et l'abonnement actif de l'utilisateur connecté
    // ─────────────────────────────────────────────────────────────────────────

    public function quota(Request $request): JsonResponse
    {
        $user              = $request->user();
        $abonnementActif   = $user->abonnementActif();

        return response()->json([
            'success' => true,
            'data'    => [
                'essais_gratuits_restants'  => $user->essais_gratuits_restants,
                'peut_publier'              => $user->peutPublier(),
                'abonnement_actif'          => $abonnementActif ? [
                    'id'                        => $abonnementActif->id,
                    'plan'                      => $abonnementActif->plan->nom,
                    'nb_publications_restantes' => $abonnementActif->nb_publications_restantes,
                    'nb_publications_initiales' => $abonnementActif->nb_publications_initiales,
                    'date_achat'                => $abonnementActif->date_achat->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/client/abonnements/historique
    // Historique des abonnements de l'utilisateur
    // ─────────────────────────────────────────────────────────────────────────

    public function historique(Request $request): JsonResponse
    {
        $abonnements = UserAbonnement::with('plan')
            ->where('user_id', $request->user()->id)
            ->latest('date_achat')
            ->get()
            ->map(fn($a) => [
                'id'                        => $a->id,
                'plan'                      => $a->plan->nom,
                'prix_paye'                 => (float) $a->plan->prix,
                'nb_publications_initiales' => $a->nb_publications_initiales,
                'nb_publications_restantes' => $a->nb_publications_restantes,
                'statut'                    => $a->statut,
                'date_achat'                => $a->date_achat->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $abonnements,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/client/abonnements/acheter
    // Initier l'achat d'un plan via Semoa CashPay
    // ─────────────────────────────────────────────────────────────────────────

    public function acheter(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id'            => 'required|uuid|exists:plan_abonnements,id',
            'operateur_paiement' => 'required|string|in:TMONEY,FLOOZ,CARD,tmoney,flooz,card',
            'telephone'          => [
                'required_if:operateur_paiement,TMONEY,FLOOZ,tmoney,flooz',
                'nullable',
                'string',
                'regex:/^(\+?228)?[79]\d{7}$/',
            ],
        ], [
            'telephone.required_if' => 'Le numéro de téléphone Mobile Money est obligatoire.',
            'telephone.regex'       => 'Numéro invalide (ex: 90123456 ou +22890123456).',
        ]);

        $plan = PlanAbonnement::where('est_actif', true)->findOrFail($request->plan_id);
        $user = $request->user();

        $operateur = strtoupper($request->operateur_paiement);
        $telephone = trim($request->telephone ?? '');

        // Formater en E.164 (+228) si 8 chiffres
        if ($telephone !== '' && !str_starts_with($telephone, '+')) {
            $telephone = str_starts_with($telephone, '228')
                ? '+' . $telephone
                : '+228' . $telephone;
        }

        $montant   = (float) $plan->prix;
        $reference = 'ABO-' . strtoupper(substr($plan->id, 0, 8));

        if ($montant <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Ce plan est gratuit. Contactez l\'administration.',
            ], 422);
        }

        // Idempotence : éviter les doubles achats en moins de 15 minutes
        $existingPaiement = Paiement::where('type_paiement', 'abonnement')
            ->where('statut', 'initie')
            ->whereHasMorph('payable', [UserAbonnement::class], fn($q) => $q->where('user_id', $user->id)->where('plan_id', $plan->id))
            ->where('created_at', '>=', now()->subMinutes(15))
            ->latest()
            ->first();

        if ($existingPaiement) {
            return response()->json([
                'success' => true,
                'message' => 'Une demande de paiement est déjà en cours pour ce plan.',
                'data'    => [
                    'paiement_id' => $existingPaiement->id,
                    'bill_id'     => $existingPaiement->semoa_bill_id,
                    'montant'     => $montant,
                    'operateur'   => $operateur,
                    'statut'      => 'initie',
                ],
            ]);
        }

        DB::beginTransaction();
        try {
            // 1. Créer la ligne user_abonnement en attente
            $userAbonnement = UserAbonnement::create([
                'user_id'                   => $user->id,
                'plan_id'                   => $plan->id,
                'nb_publications_initiales' => $plan->nb_publications,
                'nb_publications_restantes' => $plan->nb_publications,
                'statut'                    => 'annule', // Sera activé après paiement confirmé
                'date_achat'                => now(),
            ]);

            // 2. Créer le paiement lié (polymorphique)
            $paiement = Paiement::create([
                'type_paiement'         => 'abonnement',
                'payable_type'          => UserAbonnement::class,
                'payable_id'            => $userAbonnement->id,
                'montant'               => $montant,
                'operateur_paiement'    => $operateur,
                'reference_transaction' => $reference,
                'statut'                => 'initie',
            ]);

            // 3. Appeler Semoa
            $semoa       = app(SemoaService::class);
            $callbackUrl = url('/api/webhooks/semoa?paiement_id=' . $paiement->id);

            $result = $semoa->createOrder([
                'montant'      => $montant,
                'telephone'    => $telephone,
                'operateur'    => $operateur,
                'reference'    => $reference . '-' . $paiement->id,
                'description'  => "Abonnement ImmoPro — {$plan->nom} ({$plan->nb_publications} publications)",
                'callback_url' => $callbackUrl,
                'redirect_url' => 'immopro://abonnement/retour?statut=paye&paiement_id=' . $paiement->id,
            ]);

            // 4. Déclencher le PUSH USSD si Mobile Money
            if (in_array($operateur, ['TMONEY', 'FLOOZ']) && !empty($telephone) && !empty($result['order_reference'])) {
                try {
                    $semoa->triggerDirectPay($result['order_reference'], $operateur, $telephone);
                } catch (\Throwable $e) {
                    Log::warning('[AbonnementPaiement] triggerDirectPay échoué (non bloquant)', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 5. Sauvegarder la référence Semoa
            $paiement->update([
                'reference_transaction' => $result['order_reference'] ?? $reference,
                'semoa_bill_id'         => $result['order_reference'] ?? null,
            ]);

            DB::commit();

            $instructions = match($operateur) {
                'TMONEY' => "Une notification PUSH T-Money a été envoyée. Confirmez le paiement de " . number_format($montant, 0, ',', ' ') . " FCFA. Sinon composez #145#.",
                'FLOOZ'  => "Une notification PUSH Flooz a été envoyée. Confirmez le paiement de " . number_format($montant, 0, ',', ' ') . " FCFA. Sinon composez *155#.",
                'CARD'   => "Rendez-vous sur le portail de paiement sécurisé.",
                default  => "Suivez les instructions de votre opérateur.",
            };

            return response()->json([
                'success' => true,
                'message' => 'Demande de paiement envoyée.',
                'data'    => [
                    'paiement_id'       => $paiement->id,
                    'abonnement_id'     => $userAbonnement->id,
                    'bill_id'           => $result['order_reference'] ?? null,
                    'montant'           => $montant,
                    'operateur'         => $operateur,
                    'statut'            => 'initie',
                    'instructions'      => $instructions,
                    'payment_url'       => $result['bill_url'] ?? null,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[AbonnementPaiement] Erreur initiation', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initiation du paiement : ' . (app()->isLocal() ? $e->getMessage() : 'Veuillez réessayer.'),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/client/abonnements/confirmer
    // Confirmer le paiement (polling ou retour depuis bill_url)
    // ─────────────────────────────────────────────────────────────────────────

    public function confirmer(Request $request): JsonResponse
    {
        $request->validate([
            'paiement_id' => 'required|uuid|exists:paiements,id',
        ]);

        $paiement       = Paiement::with('payable')->findOrFail($request->paiement_id);
        $userAbonnement = $paiement->payable;

        // Vérifier que ce paiement appartient bien à l'utilisateur connecté
        if ($userAbonnement->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        // Déjà confirmé
        if (in_array($paiement->statut, ['confirme', 'succes'])) {
            return response()->json([
                'success' => true,
                'message' => 'Abonnement déjà activé.',
                'data'    => ['abonnement_id' => $userAbonnement->id, 'statut' => $userAbonnement->statut],
            ]);
        }

        // Échoué
        if ($paiement->statut === 'echoue') {
            return response()->json(['success' => false, 'message' => 'Ce paiement a échoué.'], 422);
        }

        // Vérifier auprès de Semoa
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
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de vérifier le statut. Veuillez réessayer.',
                ], 500);
            }
        }

        DB::beginTransaction();
        try {
            // 1. Confirmer le paiement
            $paiement->update(['statut' => 'confirme']);

            // 2. Activer l'abonnement
            $userAbonnement->update(['statut' => 'actif']);

            // 3. Générer le reçu
            $recu = Recu::create([
                'paiement_id'   => $paiement->id,
                'numero_recu'   => Recu::genererNumero(),
                'date_emission' => now(),
            ]);

            // 4. Notifier l'utilisateur
            try {
                app(\App\Services\NotificationService::class)->notify(
                    $request->user(),
                    'abonnement_active',
                    'Abonnement activé !',
                    "Votre abonnement \"{$userAbonnement->plan->nom}\" est actif. Vous avez {$userAbonnement->nb_publications_restantes} publication(s) disponible(s).",
                    ['abonnement_id' => $userAbonnement->id]
                );
            } catch (\Throwable $e) {
                Log::warning('[AbonnementPaiement] Notification échouée : ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Abonnement activé avec succès !',
                'data'    => [
                    'abonnement_id'             => $userAbonnement->id,
                    'plan'                      => $userAbonnement->plan->nom,
                    'nb_publications_restantes' => $userAbonnement->nb_publications_restantes,
                    'statut'                    => 'actif',
                    'recu'                      => [
                        'id'          => $recu->id,
                        'numero_recu' => $recu->numero_recu,
                        'date'        => $recu->date_emission->toIso8601String(),
                    ],
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[AbonnementPaiement] Erreur confirmation', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'activation de l\'abonnement.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }
}
