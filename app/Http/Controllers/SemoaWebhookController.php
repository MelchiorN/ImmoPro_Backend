<?php

namespace App\Http\Controllers;

use App\Models\Paiement;
use App\Models\Location;
use App\Models\Recu;
use App\Models\Commission;
use App\Models\Reversement;
use App\Models\UserAbonnement;
use App\Models\Visite;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Contrôleur Webhook Semoa CashPay
 *
 * Endpoint public (sans auth Sanctum) appelé par Semoa lorsqu'un paiement
 * est confirmé, échoué ou annulé.
 *
 * Route : POST /api/webhooks/semoa?paiement_id={uuid}
 *
 * Payload Semoa attendu (exemple) :
 * {
 *   "id": "bill_xxx",
 *   "reference": "LOC-XXXXXXXX-uuid",
 *   "status": "PAID",
 *   "amount": 250000,
 *   "operator": "TMONEY",
 *   "transaction_id": "TG2024XXXXXXXXXX"
 * }
 */
class SemoaWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $paiementId = $request->query('paiement_id');
        $payload    = $request->all();

        Log::info('[Semoa Webhook] Réception', [
            'paiement_id' => $paiementId,
            'payload'     => $payload,
        ]);

        // ── Validation du payload ─────────────────────────────────────────────
        if (! $paiementId) {
            return response()->json(['success' => false, 'message' => 'paiement_id manquant.'], 400);
        }

        $paiement = Paiement::with(['location.bien', 'location.locataire', 'location.proprietaire', 'payable'])
            ->find($paiementId);

        if (! $paiement) {
            Log::warning('[Semoa Webhook] Paiement introuvable', ['paiement_id' => $paiementId]);
            return response()->json(['success' => false, 'message' => 'Paiement introuvable.'], 404);
        }

        // Ignorer si le paiement a déjà été traité ('confirme' ou ancien 'succes' rétro-compat)
        if (in_array($paiement->statut, ['confirme', 'succes'])) {
            return response()->json(['success' => true, 'message' => 'Déjà traité.']);
        }

        $status = strtoupper($payload['state'] ?? $payload['status'] ?? '');

        // ── Paiement Réussi (PAID) ─────────────────────────────────────
        if ($status === 'PAID') {
            // Router vers le bon handler selon le type de paiement
            return match($paiement->type_paiement ?? 'location') {
                'abonnement'  => $this->confirmerAbonnement($paiement, $payload),
                'frais_etude' => $this->confirmerFraisEtude($paiement, $payload),
                'visite'      => $this->confirmerVisite($paiement, $payload),
                default       => $this->confirmerPaiement($paiement, $payload),
            };
        }

        // ── Paiement Échoué ou Annulé ──────────────────────────────────
        if (in_array($status, ['CANCELLED', 'FAILED', 'EXPIRED', 'ERROR'])) {
            $paiement->update([
                'statut'                => 'echoue',
                'reference_transaction' => $payload['order_reference'] ?? $payload['transaction_id'] ?? $paiement->reference_transaction,
            ]);

            // Notifier l'utilisateur du paiement échoué/annulé
            try {
                $user = $this->getUserFromPaiement($paiement);
                if ($user) {
                    $estAnnule = in_array($status, ['CANCELLED']);
                    $typeNotif = $estAnnule ? 'paiement_annule' : 'paiement_echoue';
                    $titreNotif = $estAnnule ? 'Paiement annulé' : 'Paiement échoué';
                    $msgNotif   = $estAnnule
                        ? 'Votre paiement a été annulé. Vous pouvez réessayer depuis l\'application.'
                        : 'Votre paiement a échoué. Veuillez réessayer ou choisir un autre moyen de paiement.';

                    app(NotificationService::class)->notify(
                        $user,
                        $typeNotif,
                        $titreNotif,
                        $msgNotif,
                        ['paiement_id' => (string) $paiement->id, 'type' => $paiement->type_paiement],
                        "❌ {$titreNotif} — ImmoPro",
                        EmailTemplateService::generic(
                            $titreNotif,
                            $msgNotif,
                            [
                                ['icon' => '💳', 'label' => 'Montant', 'value' => number_format((float) $paiement->montant, 0, ',', ' ') . ' FCFA'],
                                ['icon' => '📱', 'label' => 'Opérateur', 'value' => $paiement->operateur_paiement ?? '—'],
                                ['icon' => '🔢', 'label' => 'Statut Semoa', 'value' => $status],
                            ],
                            null,
                            'Si vous pensez qu\'il s\'agit d\'une erreur, contactez notre support.'
                        )
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[Semoa Webhook] Notification échec paiement échouée : ' . $e->getMessage());
            }

            Log::warning('[Semoa Webhook] Paiement échoué/annulé', [
                'paiement_id' => $paiementId,
                'status'      => $status,
            ]);

            return response()->json(['success' => true, 'message' => 'Statut mis à jour : ' . $status]);
        }

        return response()->json(['success' => true, 'message' => 'Statut ignoré : ' . $status]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Confirme le paiement → active la location + génère reçu + commission
    // ─────────────────────────────────────────────────────────────────────────

    private function confirmerPaiement(Paiement $paiement, array $payload): JsonResponse
    {
        $location = $paiement->location;

        if (! $location) {
            return response()->json(['success' => false, 'message' => 'Location introuvable.'], 404);
        }

        DB::beginTransaction();
        try {
            // 1. Marquer le paiement comme confirmé
            $paiement->update([
                'statut'                => 'confirme',
                'reference_transaction' => $payload['order_reference'] ?? $payload['transaction_id'] ?? $paiement->reference_transaction,
                'semoa_bill_id'         => $payload['order_reference'] ?? $paiement->semoa_bill_id,
            ]);

            // 2. Activer la location
            $location->update(['statut' => 'actif']);

            // 3. Mettre le bien en "loué"
            if ($location->bien) {
                $location->bien->update(['statut' => 'loue']);
            }

            // 4. Créer le reçu de paiement
            $numeroRecu = 'REC-' . now()->format('Ymd') . '-' . strtoupper(substr($paiement->id, 0, 6));
            $recu = Recu::create([
                'paiement_id'        => $paiement->id,
                'numero_recu'        => $numeroRecu,
                'date_emission'      => now(),
                'montant'            => $paiement->montant,
                'operateur_paiement' => $paiement->operateur_paiement,
            ]);

            // Tenter de générer le PDF du reçu
            try {
                $cheminPdf = $this->genererPdfRecu($recu, $location, $paiement);
                if ($cheminPdf) {
                    $recu->update(['fichier_pdf' => $cheminPdf]);
                }
            } catch (\Throwable $e) {
                Log::warning("[Semoa Webhook] Génération PDF reçu échouée: " . $e->getMessage());
            }

            // 5. Créer la commission ImmoPro (si elle n'existe pas encore)
            $commissionExistante = Commission::where('location_id', $location->id)->first();
            if (! $commissionExistante) {
                Commission::create([
                    'location_id' => $location->id,
                    'montant'     => $location->montant_commission,
                    'statut'      => 'percue',
                ]);
            }

            // 6. Créer le reversement au propriétaire
            $reversementExistant = Reversement::where('location_id', $location->id)->first();
            if (! $reversementExistant) {
                Reversement::create([
                    'location_id'    => $location->id,
                    'proprietaire_id'=> $location->proprietaire_id,
                    'montant'        => (float) $location->prix_proprietaire * (int) $location->duree_mois,
                    'statut'         => 'en_attente',
                ]);
            }

            // 7. Notifier le locataire (paiement confirmé + location active)
            try {
                if ($location->locataire) {
                    $operateurLabel = match(strtoupper($paiement->operateur_paiement ?? '')) {
                        'CARD'   => 'Carte bancaire',
                        'TMONEY' => 'T-Money',
                        'FLOOZ'  => 'Moov Flooz',
                        default  => $paiement->operateur_paiement ?? '—',
                    };
                    app(NotificationService::class)->notify(
                        $location->locataire,
                        'paiement_confirme',
                        '✅ Paiement confirmé !',
                        "Votre paiement de location pour \"{$location->bien?->titre}\" a été confirmé. Votre location est maintenant active.",
                        ['location_id' => (string) $location->id, 'recu' => $numeroRecu],
                        '✅ Votre paiement de location est confirmé — ImmoPro',
                        EmailTemplateService::generic(
                            'Paiement de location confirmé !',
                            "Votre paiement pour la location du bien <strong>{$location->bien?->titre}</strong> a été reçu et confirmé avec succès.",
                            [
                                ['icon' => '🏠', 'label' => 'Bien',          'value' => $location->bien?->titre ?? '—'],
                                ['icon' => '📅', 'label' => 'Durée',         'value' => $location->duree_mois . ' mois'],
                                ['icon' => '💰', 'label' => 'Montant payé',  'value' => number_format((float) $paiement->montant, 0, ',', ' ') . ' FCFA'],
                                ['icon' => '💳', 'label' => 'Opérateur',     'value' => $operateurLabel],
                                ['icon' => '🧾', 'label' => 'Reçu',          'value' => $numeroRecu],
                            ],
                            null,
                            'Votre location est désormais active. Contactez votre propriétaire pour les prochaines étapes.'
                        )
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[Semoa Webhook] Notification location locataire échouée : ' . $e->getMessage());
            }

            DB::commit();

            Log::info('[Semoa Webhook] Paiement confirmé avec succès', [
                'paiement_id' => $paiement->id,
                'location_id' => $location->id,
                'recu'        => $numeroRecu,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paiement confirmé. Location activée.',
                'data'    => [
                    'recu'        => $numeroRecu,
                    'location_id' => $location->id,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Semoa Webhook] Erreur confirmation', [
                'paiement_id' => $paiement->id,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation du paiement.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Confirme un paiement d'abonnement → active le UserAbonnement + génère reçu
    // ─────────────────────────────────────────────────────────────────────────

    private function confirmerAbonnement(Paiement $paiement, array $payload): JsonResponse
    {
        /** @var UserAbonnement $userAbonnement */
        $userAbonnement = $paiement->payable;

        if (! $userAbonnement) {
            return response()->json(['success' => false, 'message' => 'Abonnement introuvable.'], 404);
        }

        DB::beginTransaction();
        try {
            // 1. Confirmer le paiement
            $paiement->update([
                'statut'                => 'confirme',
                'reference_transaction' => $payload['order_reference'] ?? $paiement->reference_transaction,
                'semoa_bill_id'         => $payload['order_reference'] ?? $paiement->semoa_bill_id,
            ]);

            // 2. Activer l'abonnement (fusion si un abonnement actif existe déjà)
            $abonnementResultat = $userAbonnement->activerEnFusionnantSiNecessaire();
            $estFusionne        = $abonnementResultat->id !== $userAbonnement->id;

            // 3. Générer le reçu
            $recu = Recu::create([
                'paiement_id'   => $paiement->id,
                'numero_recu'   => Recu::genererNumero(),
                'date_emission' => now(),
            ]);

            // 4. Notifier l'utilisateur
            try {
                $user = $userAbonnement->user;
                $operateurLabel = match(strtoupper($paiement->operateur_paiement ?? '')) {
                    'CARD'   => 'Carte bancaire',
                    'TMONEY' => 'T-Money',
                    'FLOOZ'  => 'Moov Flooz',
                    default  => $paiement->operateur_paiement ?? '—',
                };

                $messageFusion = $estFusionne
                    ? "Vos publications ont été ajoutées à votre abonnement en cours. Vous avez maintenant {$abonnementResultat->nb_publications_restantes} publication(s) disponible(s)."
                    : "Votre abonnement \"{$userAbonnement->plan->nom}\" est activé. Vous avez {$abonnementResultat->nb_publications_restantes} publication(s) disponible(s).";

                app(NotificationService::class)->notify(
                    $user,
                    'abonnement_active',
                    '🎉 Abonnement activé !',
                    $messageFusion,
                    ['abonnement_id' => $abonnementResultat->id],
                    '🎉 Votre abonnement ImmoPro est actif !',
                    EmailTemplateService::generic(
                        'Abonnement activé avec succès !',
                        $estFusionne
                            ? "Vos nouvelles publications ont été ajoutées à votre abonnement <strong>{$abonnementResultat->plan->nom}</strong> en cours."
                            : "Votre abonnement <strong>{$userAbonnement->plan->nom}</strong> est maintenant actif. Vous pouvez dès à présent publier vos annonces immobilières.",
                        [
                            ['icon' => '📦', 'label' => 'Plan',                    'value' => $userAbonnement->plan->nom],
                            ['icon' => '📋', 'label' => 'Publications disponibles','value' => $abonnementResultat->nb_publications_restantes . ' publication(s)'],
                            ['icon' => '🧾', 'label' => 'Reçu',                   'value' => $recu->numero_recu],
                            ['icon' => '💳', 'label' => 'Opérateur',              'value' => $operateurLabel],
                            ['icon' => '💰', 'label' => 'Montant payé',           'value' => number_format((float) $paiement->montant, 0, ',', ' ') . ' FCFA'],
                        ],
                        null,
                        'Merci de votre confiance ! Rendez-vous sur l\'application pour publier vos annonces.'
                    )
                );
            } catch (\Throwable $e) {
                Log::warning('[Semoa Webhook] Notification abonnement échouée : ' . $e->getMessage());
            }

            DB::commit();

            Log::info('[Semoa Webhook] Abonnement activé', [
                'paiement_id'     => $paiement->id,
                'abonnement_id'   => $abonnementResultat->id,
                'recu'            => $recu->numero_recu,
                'fusionne'        => $estFusionne,
            ]);

            return response()->json([
                'success' => true,
                'message' => $estFusionne ? 'Publications fusionnées sur abonnement existant.' : 'Abonnement activé.',
                'data'    => ['recu' => $recu->numero_recu, 'abonnement_id' => $abonnementResultat->id],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Semoa Webhook] Erreur activation abonnement', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur activation abonnement.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Confirme un paiement de frais d'étude → passe le bien en_attente
    // ─────────────────────────────────────────────────────────────────────────

    private function confirmerFraisEtude(Paiement $paiement, array $payload): JsonResponse
    {
        /** @var \App\Models\Bien $bien */
        $bien = $paiement->payable;

        if (! $bien instanceof \App\Models\Bien) {
            return response()->json(['success' => false, 'message' => 'Bien introuvable.'], 404);
        }

        DB::beginTransaction();
        try {
            // 1. Confirmer le paiement
            $paiement->update([
                'statut'                => 'confirme',
                'reference_transaction' => $payload['order_reference'] ?? $paiement->reference_transaction,
                'semoa_bill_id'         => $payload['order_reference'] ?? $paiement->semoa_bill_id,
            ]);

            // 2. Activer le bien (brouillon → en_attente)
            $bien->update([
                'statut'             => 'en_attente',
                'frais_etude_statut' => 'paye',
            ]);

            // 3. Décrémenter le quota du propriétaire
            $user       = $bien->proprietaire;
            $abonnement = $user?->abonnementActif();
            if ($abonnement) {
                $abonnement->consommerUnePublication();
            } elseif ($user && $user->essais_gratuits_restants > 0) {
                $user->decrement('essais_gratuits_restants');
            }

            // 4. Générer le reçu
            $recu = Recu::create([
                'paiement_id'   => $paiement->id,
                'numero_recu'   => Recu::genererNumero(),
                'date_emission' => now(),
            ]);

            // 5. Notifier admins, agents et client pour la soumission du bien
            try {
                app(NotificationService::class)->notifyNouveauBienSoumis($bien);
            } catch (\Throwable $e) {
                Log::warning('[Semoa Webhook] Notification frais_etude échouée: ' . $e->getMessage());
            }

            // 6. Notifier le client
            try {
                if ($user) {
                    app(NotificationService::class)->notify(
                        $user,
                        'frais_etude_confirme',
                        'Frais d\'étude confirmés',
                        "Vos frais d'étude pour \"{$bien->titre}\" ont été confirmés. Votre dossier est en cours d'analyse.",
                        ['bien_id' => (string) $bien->id, 'recu_id' => (string) $recu->id]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[Semoa Webhook] Notif client frais_etude échouée: ' . $e->getMessage());
            }

            DB::commit();

            Log::info('[Semoa Webhook] Frais étude confirmés', [
                'paiement_id' => $paiement->id,
                'bien_id'     => $bien->id,
                'recu'        => $recu->numero_recu,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Frais d\'étude confirmés. Bien en attente de vérification.',
                'data'    => ['recu' => $recu->numero_recu, 'bien_id' => $bien->id],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Semoa Webhook] Erreur confirmation frais_etude', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur confirmation frais d\'étude.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Confirme un paiement de frais de visite → crée la Visite + reçu + notif
    // Même pattern que confirmerAbonnement : statut 'confirme', Recu::genererNumero()
    // ─────────────────────────────────────────────────────────────────────────

    private function confirmerVisite(Paiement $paiement, array $payload): JsonResponse
    {
        /** @var \App\Models\Bien $bien */
        $bien = $paiement->payable;

        if (! $bien instanceof \App\Models\Bien) {
            Log::warning('[Semoa Webhook] Visite : bien introuvable via payable', [
                'paiement_id' => $paiement->id,
                'payable_id'  => $paiement->payable_id,
            ]);
            return response()->json(['success' => false, 'message' => 'Bien introuvable.'], 404);
        }

        // Chercher d'abord une Visite existante déjà créée par confirmerPaiement() client.
        // On recherche via le paiement lui-même pour éviter les collisions multi-clients.
        // Si le webhook arrive avant la confirmation manuelle, on la crée ici (même logique).
        $clientId = null;

        // Retrouver le client via le paiement (le paiement est lié au client authentifié)
        // La table paiements ne stocke pas directement le client, mais on peut le retrouver
        // via la visite déjà existante ou via les logs du paiement
        $visite = \App\Models\Visite::where('bien_id', $bien->id)
            ->where('type_visite', \App\Models\Visite::TYPE_CLIENT)
            ->whereNotNull('client_id')
            ->latest()
            ->first();

        DB::beginTransaction();
        try {
            // 1. Marquer le paiement comme confirmé (même statut que abonnement/frais_etude)
            $paiement->update([
                'statut'                => 'confirme',
                'reference_transaction' => $payload['order_reference'] ?? $payload['transaction_id'] ?? $paiement->reference_transaction,
                'semoa_bill_id'         => $payload['order_reference'] ?? $paiement->semoa_bill_id,
            ]);

            // 2. Marquer la visite comme payée (ou en créer une si absente)
            if ($visite) {
                if (! $visite->est_payee) {
                    $visite->update(['est_payee' => true, 'statut' => 'proposee']);
                }
                $clientId = $visite->client_id;
            } else {
                // Webhook arrivé avant que le client ait appelé confirmerPaiement().
                // On ne crée PAS de visite ici car on n'a pas de client_id.
                // Le statut 'confirme' du paiement suffira : quand le client appellera
                // confirmerPaiement(), il trouvera le paiement déjà confirmé et créera
                // la visite avec son client_id correct (idempotence gérée par firstOrCreate).
                Log::info('[Semoa Webhook] Visite : webhook arrivé avant confirmerPaiement() client — paiement marqué confirme, visite sera créée par le client.', [
                    'paiement_id' => $paiement->id,
                    'bien_id'     => $bien->id,
                ]);
                $clientId = null;
            }

            // 3. Générer le reçu (même helper que abonnement/frais_etude)
            $recu = Recu::firstOrCreate(
                ['paiement_id' => $paiement->id],
                ['numero_recu' => Recu::genererNumero(), 'date_emission' => now()]
            );
            $numeroRecu = $recu->numero_recu;

            // 4. Notifier le client
            if ($clientId && $visite) {
                $client = \App\Models\User::find($clientId);
                if ($client) {
                    app(NotificationService::class)->notify(
                        $client,
                        'visite_payee',
                        '✅ Paiement de visite confirmé !',
                        "Votre paiement pour la visite de « {$bien->titre} » a été confirmé. La localisation exacte est maintenant déverrouillée.",
                        ['visite_id' => $visite->id, 'bien_id' => $bien->id],
                        '✅ Visite confirmée — ImmoPro',
                        \App\Services\EmailTemplateService::generic(
                            'Frais de visite confirmés !',
                            "Votre paiement pour visiter <strong>{$bien->titre}</strong> a été reçu et confirmé. Vous pouvez maintenant voir la localisation exacte et planifier votre visite.",
                            [
                                ['icon' => '🏠', 'label' => 'Bien',           'value' => $bien->titre],
                                ['icon' => '💰', 'label' => 'Montant payé',   'value' => number_format((float) $paiement->montant, 0, ',', ' ') . ' FCFA'],
                                ['icon' => '🧾', 'label' => 'Reçu',           'value' => $numeroRecu],
                            ],
                            null,
                            'Connectez-vous à l\'application pour voir la localisation et planifier votre visite.'
                        )
                    );
                }
            }

            // 5. Notifier l'agent assigné
            if ($bien->agent_id && $visite) {
                $agent = \App\Models\User::find($bien->agent_id);
                if ($agent) {
                    app(NotificationService::class)->notify(
                        $agent,
                        'visite_client_payee',
                        'Visite client payée',
                        "Un client a payé les frais de visite pour « {$bien->titre} ». Préparez-vous à planifier la visite.",
                        ['visite_id' => $visite->id, 'bien_id' => $bien->id]
                    );
                }
            }

            DB::commit();

            Log::info('[Semoa Webhook] Visite confirmée', [
                'paiement_id' => $paiement->id,
                'visite_id'   => $visite?->id,
                'bien_id'     => $bien->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paiement visite confirmé. Localisation déverrouillée.',
                'data'    => ['visite_id' => $visite?->id, 'recu' => $numeroRecu],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Semoa Webhook] Erreur confirmation visite', [
                'paiement_id' => $paiement->id,
                'error'       => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur confirmation visite.'], 500);
        }
    }

    private function genererPdfRecu(Recu $recu, Location $location, Paiement $paiement): ?string
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return null;
        }

        $html = "
        <h1>REÇU DE PAIEMENT</h1>
        <p><strong>Numéro :</strong> {$recu->numero_recu}</p>
        <p><strong>Date :</strong> " . now()->format('d/m/Y H:i') . "</p>
        <p><strong>Bien :</strong> " . ($location->bien?->adresse ?? 'Immeuble ImmoPro') . "</p>
        <p><strong>Durée :</strong> {$location->duree_mois} mois</p>
        <p><strong>Montant payé :</strong> " . number_format((float) $paiement->montant, 0, ',', ' ') . " FCFA</p>
        <p><strong>Opérateur :</strong> {$paiement->operateur_paiement}</p>
        <p><strong>Référence :</strong> {$paiement->reference_transaction}</p>
        ";

        $pdf    = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $annee  = now()->year;
        $chemin = "recus/{$annee}/{$recu->numero_recu}.pdf";

        Storage::disk('local')->put($chemin, $pdf->output());
        return $chemin;
    }

    /**
     * Helper : récupère le User propriétaire d'un paiement selon son type.
     */
    private function getUserFromPaiement(Paiement $paiement): ?\App\Models\User
    {
        return match($paiement->type_paiement ?? '') {
            'abonnement'  => $paiement->payable?->user,                // UserAbonnement->user
            'frais_etude' => $paiement->payable?->proprietaire,         // Bien->proprietaire
            'location'    => $paiement->location?->locataire,           // Location->locataire
            'visite'      => \App\Models\Visite::where('bien_id', $paiement->payable_id)
                                ->where('type_visite', \App\Models\Visite::TYPE_CLIENT)
                                ->latest()->first()?->client,
            default       => null,
        };
    }
}
