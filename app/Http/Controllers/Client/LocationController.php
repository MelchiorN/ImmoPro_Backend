<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Commission;
use App\Models\Contrat;
use App\Models\Location;
use App\Models\Paiement;
use App\Models\Recu;
use App\Models\Reversement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\Payment\SemoaService;

class LocationController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/mobile/locations/initier
    // Le client choisit la durée → vérifie les règles → verrouille le bien
    // → crée la location + le contrat PDF
    // ─────────────────────────────────────────────────────────────────────────

    public function initier(Request $request): JsonResponse
    {
        $request->validate([
            'bien_id'    => 'required|uuid|exists:biens,id',
            'date_debut' => 'required|date|after_or_equal:today',
            'duree_mois' => 'required|integer|min:1|max:60',
        ]);

        $bien = Bien::findOrFail($request->bien_id);

        // Règle : le client ne peut pas louer son propre bien
        if ($bien->user_id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas louer votre propre bien.',
            ], 403);
        }

        // Règle : le bien doit être publié
        if ($bien->statut !== 'publie') {
            return response()->json([
                'success' => false,
                'message' => 'Ce bien n\'est plus disponible.',
            ], 422);
        }

        // Règle : le bien doit être de type location
        if ($bien->type_transaction !== 'location') {
            return response()->json([
                'success' => false,
                'message' => 'Ce bien n\'est pas disponible à la location.',
            ], 422);
        }

        // Anti-concurrence : vérifier si le bien est déjà verrouillé
        if ($bien->estVerrouille()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce bien est temporairement réservé. Veuillez réessayer dans quelques minutes.',
            ], 409);
        }

        DB::beginTransaction();
        try {
            // Calculer les montants
            $dateDebut     = \Carbon\Carbon::parse($request->date_debut);
            $dateFin       = $dateDebut->copy()->addMonths($request->duree_mois);
            $prixPublic    = (float) $bien->prix_public;
            $prixProprio   = (float) $bien->prix;
            $montantTotal  = round($prixPublic * $request->duree_mois, 2);
            $montantCommission = round(($prixPublic - $prixProprio) * $request->duree_mois, 2);

            // Verrouiller le bien (15 minutes)
            $bien->verrouiller(15);

            // Créer la location
            $location = Location::create([
                'bien_id'            => $bien->id,
                'locataire_id'       => $request->user()->id,
                'proprietaire_id'    => $bien->user_id,
                'date_debut'         => $dateDebut->toDateString(),
                'date_fin'           => $dateFin->toDateString(),
                'duree_mois'         => $request->duree_mois,
                'prix_proprietaire'  => $prixProprio,
                'montant_commission' => $montantCommission,
                'montant_total'      => $montantTotal,
                'statut'             => 'en_attente_contrat',
            ]);

            // Générer le contrat
            $contenuHtml = $this->genererContenuContrat($location, $bien, $request->user());
            $contrat = Contrat::create([
                'location_id'      => $location->id,
                'contenu_html'     => $contenuHtml,
                'fichier_pdf'      => null,
                'url_pdf'          => null,
                'date_generation'  => now(),
                'date_creation'    => now(),
                'statut_signature' => 'en_attente',
            ]);

            // Tenter de générer le PDF si dompdf est disponible
            try {
                $cheminPdf = $this->genererPdfContrat($contrat, $location);
                if ($cheminPdf) {
                    $contrat->update([
                        'fichier_pdf' => $cheminPdf,
                        'url_pdf'     => $cheminPdf,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("Génération PDF contrat échouée: " . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success'  => true,
                'message'  => 'Location initiée. Veuillez lire et accepter le contrat.',
                'data'     => [
                    'location_id'   => $location->id,
                    'montant_total' => $montantTotal,
                    'duree_mois'    => $request->duree_mois,
                    'date_debut'    => $dateDebut->toDateString(),
                    'date_fin'      => $dateFin->toDateString(),
                    'contrat'       => [
                        'id'              => $contrat->id,
                        'idContrat'       => $contrat->id,
                        'location_id'     => $location->id,
                        'urlPdf'          => $contrat->urlPdf,
                        'dateCreation'    => $contrat->dateCreation?->toIso8601String(),
                        'statutSignature' => $contrat->statutSignature,
                        'contenu_html'    => $contrat->contenu_html,
                    ],
                    'locked_until'  => $bien->locked_until?->toIso8601String(),
                ],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            $bien->deverrouiller();
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'initiation de la location.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/mobile/locations/{id}/accepter-contrat
    // Le client accepte et signe le contrat → statut passe en_attente_paiement
    // ─────────────────────────────────────────────────────────────────────────

    public function accepterContrat(Request $request, string $id): JsonResponse
    {
        $location = Location::with('contrat')
            ->where('locataire_id', $request->user()->id)
            ->findOrFail($id);

        if ($location->statut !== 'en_attente_contrat') {
            return response()->json([
                'success' => false,
                'message' => 'Cette location n\'est pas en attente de signature de contrat.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $location->contrat->update([
                'statut_signature'  => 'signe',
                'date_acceptation'  => now(),
            ]);

            $location->update(['statut' => 'en_attente_paiement']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contrat accepté. Vous pouvez maintenant procéder au paiement.',
                'data'    => [
                    'location_id'   => $location->id,
                    'statut'        => $location->statut,
                    'montant_total' => (float) $location->montant_total,
                    'operateurs'    => ['Orange Money', 'Wave', 'MTN MoMo', 'Moov Money'],
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'acceptation du contrat.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/mobile/locations/{id}/refuser-contrat
    // Le client décline / refuse le contrat -> annule la location & déverrouille le bien
    // ─────────────────────────────────────────────────────────────────────────

    public function refuserContrat(Request $request, string $id): JsonResponse
    {
        $location = Location::with(['bien', 'contrat'])
            ->where('locataire_id', $request->user()->id)
            ->findOrFail($id);

        DB::beginTransaction();
        try {
            // 1. Marquer la location comme annulée
            $location->update(['statut' => 'annule']);

            // 2. Marquer le contrat comme refusé
            if ($location->contrat) {
                $location->contrat->update([
                    'statut_signature' => 'refuse',
                ]);
            }

            // 3. Déverrouiller le bien pour libérer la réservation
            if ($location->bien) {
                $location->bien->deverrouiller();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contrat refusé. La réservation a été annulée et le bien est de nouveau disponible.',
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du refus du contrat.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/mobile/locations/{id}/payer
    // Initie le paiement via Semoa CashPay API V2.0
    // Opérateurs supportés : TMONEY | FLOOZ | CARD
    // ─────────────────────────────────────────────────────────────────────────

    public function payer(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'operateur_paiement' => 'required|string|in:TMONEY,FLOOZ,CARD,tmoney,flooz,card',
            'telephone'          => [
                'required_if:operateur_paiement,TMONEY,FLOOZ,tmoney,flooz',
                'nullable',
                'string',
                'regex:/^(\+?228)?[79]\d{7}$/',
            ],
        ], [
            'telephone.required_if' => 'Le numéro de téléphone Mobile Money est obligatoire.',
            'telephone.regex'       => 'Le numéro de téléphone saisi est invalide (ex: 90123456 ou +22890123456).',
        ]);

        $location = Location::with(['locataire', 'bien'])
            ->where('locataire_id', $request->user()->id)
            ->findOrFail($id);

        if ($location->statut !== 'en_attente_paiement') {
            return response()->json([
                'success' => false,
                'message' => 'Cette location n\'est pas en attente de paiement.',
            ], 422);
        }

        $operateur  = strtoupper($request->operateur_paiement);
        $telephone  = trim($request->telephone ?? '');

        // Formater en E.164 (+228) si 8 chiffres fournis
        if ($telephone !== '' && !str_starts_with($telephone, '+')) {
            if (str_starts_with($telephone, '228')) {
                $telephone = '+' . $telephone;
            } else {
                $telephone = '+228' . $telephone;
            }
        }

        if (in_array($operateur, ['TMONEY', 'FLOOZ']) && empty($telephone)) {
            return response()->json([
                'success' => false,
                'message' => 'Le numéro de téléphone est obligatoire pour valider le paiement Mobile Money.',
            ], 422);
        }
        $reference  = 'LOC-' . strtoupper(substr($location->id, 0, 8));
        $montant    = (float) $location->montant_total;

        // Si le montant total dans la DB est à 0, tenter de le recalculer depuis le prix public du bien
        if ($montant <= 0) {
            $prixPublic = (float) ($location->bien?->prix_public ?? 0);
            if ($prixPublic > 0 && $location->duree_mois > 0) {
                $montant = round($prixPublic * $location->duree_mois, 2);
                $location->update(['montant_total' => $montant]);
            }
        }

        if ($montant <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Le montant de la location doit être strictement supérieur à 0 FCFA pour être envoyé à la passerelle Semoa.',
            ], 422);
        }

        // ── 0. Vérification d'Idempotence ────────────────────────────────────
        // Évite la création de factures Semoa en double en cas de double-clic ou de re-soumission rapide.
        $existingPaiement = Paiement::where('location_id', $location->id)
            ->where('statut', 'initie')
            ->where('operateur_paiement', $operateur)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->latest()
            ->first();

        if ($existingPaiement && $existingPaiement->semoa_bill_id) {
            Log::info('[Paiement Semoa] Idempotence : réutilisation de la facture existante', [
                'location_id'   => $location->id,
                'paiement_id'   => $existingPaiement->id,
                'semoa_bill_id' => $existingPaiement->semoa_bill_id,
            ]);

            $instructions = match($operateur) {
                'TMONEY' => "Composez #145# sur votre téléphone Togo Cellulaire pour confirmer le paiement T-Money de " . number_format($montant, 0, ',', ' ') . " FCFA.",
                'FLOOZ'  => "Composez *155# sur votre téléphone Moov Africa. Une notification PUSH va apparaître pour confirmer le paiement Flooz de " . number_format($montant, 0, ',', ' ') . " FCFA.",
                'CARD'   => "Vous allez être redirigé vers le portail de paiement sécurisé.",
                default  => "Suivez les instructions de votre opérateur.",
            };

            return response()->json([
                'success'     => true,
                'message'     => 'Demande de paiement déjà en cours.',
                'data'        => [
                    'paiement_id'       => $existingPaiement->id,
                    'bill_id'           => $existingPaiement->semoa_bill_id,
                    'montant'           => $montant,
                    'operateur'         => $operateur,
                    'statut'            => 'initie',
                    'instructions'      => $instructions,
                    'payment_url'       => 'https://sandbox.cashpay.tg/facture/' . $existingPaiement->semoa_bill_id,
                ],
            ]);
        }

        DB::beginTransaction();
        try {
            // ── 1. Créer l'enregistrement de paiement local ───────────────
            $paiement = Paiement::create([
                'location_id'           => $location->id,
                'montant'               => $montant,
                'operateur_paiement'    => $operateur,
                'reference_transaction' => $reference,
                'statut'                => 'initie',
            ]);

            // ── 2. Appeler Semoa CashPay API ─────────────────────────────
            $semoa = app(SemoaService::class);
            $callbackUrl = url('/api/webhooks/semoa?paiement_id=' . $paiement->id);

            $result = $semoa->createOrder([
                'montant'      => $montant,
                'telephone'    => $telephone,
                'operateur'    => $operateur,
                'reference'    => $reference . '-' . $paiement->id,
                'description'  => "Location bien : {$location->bien?->titre} — {$location->duree_mois} mois",
                'callback_url' => $callbackUrl,
            ]);

            // ── 3. Sauvegarder la référence Semoa ───────────────────────
            $paiement->update([
                'reference_transaction' => $result['order_reference'] ?? $reference,
                'semoa_bill_id'         => $result['order_reference'] ?? null,
            ]);

            DB::commit();

            // ── Construire les instructions selon l'opérateur ─────────────
            $billUrl = $result['bill_url'] ?? null;
            $instructions = match($operateur) {
                'TMONEY' => "Composez #145# sur votre téléphone Togo Cellulaire pour confirmer le paiement T-Money de " . number_format($montant, 0, ',', ' ') . " FCFA.",
                'FLOOZ'  => "Composez *155# sur votre téléphone Moov Africa. Une notification PUSH va apparaître pour confirmer le paiement Flooz de " . number_format($montant, 0, ',', ' ') . " FCFA.",
                'CARD'   => "Vous allez être redirigé vers le portail de paiement sécurisé.",
                default  => "Suivez les instructions de votre opérateur.",
            };

            return response()->json([
                'success'     => true,
                'message'     => 'Demande de paiement envoyée via Semoa.',
                'data'        => [
                    'paiement_id'       => $paiement->id,
                    'bill_id'           => $result['order_reference'] ?? null,
                    'montant'           => $montant,
                    'operateur'         => $operateur,
                    'statut'            => 'initie',
                    'instructions'      => $instructions,
                    'payment_url'       => $billUrl,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Paiement Semoa] Erreur initiation', [
                'location_id' => $id,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initiation du paiement Semoa : ' . (app()->isLocal() ? $e->getMessage() : 'Veuillez réessayer.'),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/mobile/locations/{id}/confirmer-paiement
    // Callback de confirmation → crée reçu, commission, reversement
    // → bien passe en "loué", notification au client
    // ─────────────────────────────────────────────────────────────────────────

    public function confirmerPaiement(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'paiement_id'           => 'required|uuid|exists:paiements,id',
            'reference_transaction' => 'nullable|string|max:255',
        ]);

        $location = Location::with(['bien', 'contrat'])->findOrFail($id);
        $paiement = Paiement::where('location_id', $location->id)->findOrFail($request->paiement_id);

        // Si le paiement est déjà traité avec succès, on renvoie directement le reçu existant
        if ($paiement->statut === 'confirme' || $paiement->statut === 'succes') {
            $recu = Recu::where('paiement_id', $paiement->id)->first();
            return response()->json([
                'success' => true,
                'message' => 'Paiement déjà confirmé !',
                'data'    => [
                    'location_id' => $location->id,
                    'statut'      => 'actif',
                    'recu'        => $recu ? [
                        'id'          => $recu->id,
                        'numero_recu' => $recu->numero_recu,
                        'date'        => $recu->date_emission->toIso8601String(),
                    ] : null,
                    'dates'       => [
                        'debut' => $location->date_debut->toDateString(),
                        'fin'   => $location->date_fin->toDateString(),
                    ],
                ],
            ]);
        }

        // Si le paiement est échoué, on bloque
        if ($paiement->statut === 'echoue') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement a échoué ou a été annulé.',
            ], 422);
        }

        // Si le paiement est toujours initié, on vérifie son statut réel auprès de Semoa
        if ($paiement->statut === 'initie') {
            $semoa = app(SemoaService::class);
            
            // Si on n'est pas en simulation, on interroge Semoa
            if (!config('services.semoa.simulate')) {
                try {
                    $order = $semoa->getOrder($paiement->reference_transaction);
                    $state = strtoupper($order['state'] ?? 'PENDING');

                    if ($state === 'PAID') {
                        // Le paiement est payé, on continue pour le confirmer
                    } elseif (in_array($state, ['CANCELLED', 'FAILED', 'EXPIRED', 'ERROR'])) {
                        $paiement->update(['statut' => 'echoue']);
                        return response()->json([
                            'success' => false,
                            'message' => 'Le paiement a été rejeté ou annulé par l\'opérateur.',
                        ], 422);
                    } else {
                        // Toujours en attente (Pending, etc.)
                        return response()->json([
                            'success' => false,
                            'message' => 'Le paiement est toujours en cours. Veuillez valider la transaction sur votre téléphone puis réessayer.',
                        ], 200); // Code 200 avec success: false pour permettre au client de retenter
                    }
                } catch (\Throwable $e) {
                    Log::error('[Paiement Semoa] Échec de vérification du statut', [
                        'paiement_id' => $paiement->id,
                        'error'       => $e->getMessage(),
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Impossible de vérifier le statut auprès de Semoa. Veuillez réessayer.',
                    ], 500);
                }
            }
        }

        DB::beginTransaction();
        try {
            // Récupérer le pourcentage de commission appliqué
            $categorie            = $location->bien->getCategorie();
            $pourcentageApplique  = $categorie ? (float) $categorie->pourcentage_commission : 0;

            // 1. Valider le paiement
            $paiement->update([
                'statut'                => 'confirme',
                'reference_transaction' => $request->reference_transaction ?? $paiement->reference_transaction,
            ]);

            // 2. Générer le reçu
            $numeroRecu = Recu::genererNumero();
            $recu = Recu::create([
                'paiement_id'  => $paiement->id,
                'numero_recu'  => $numeroRecu,
                'fichier_pdf'  => null,
                'date_emission'=> now(),
            ]);

            // Tenter de générer le PDF du reçu
            try {
                $cheminPdf = $this->genererPdfRecu($recu, $location, $paiement);
                if ($cheminPdf) {
                    $recu->update(['fichier_pdf' => $cheminPdf]);
                }
            } catch (\Throwable $e) {
                Log::warning("Génération PDF reçu échouée: " . $e->getMessage());
            }

            // 3. Enregistrer la commission (journal comptable ImmoPro)
            Commission::create([
                'location_id'          => $location->id,
                'paiement_id'          => $paiement->id,
                'pourcentage_applique' => $pourcentageApplique,
                'montant_gagne'        => (float) $location->montant_commission,
                'date_prelevement'     => now(),
            ]);

            // 4. Créer le reversement (dette envers le propriétaire)
            Reversement::create([
                'proprietaire_id'   => $location->proprietaire_id,
                'location_id'       => $location->id,
                'montant_a_reverser'=> (float) $location->prix_proprietaire * $location->duree_mois,
                'statut'            => 'en_attente',
            ]);

            // 5. Mettre à jour le statut de la location
            $location->update(['statut' => 'actif']);

            // 6. Déverrouiller le bien et le passer en "loué"
            $location->bien->update([
                'statut'       => 'archive', // Le bien n'est plus disponible
                'locked_until' => null,
            ]);

            // 7. Notifier le client et le propriétaire
            try {
                $notificationService = app(\App\Services\NotificationService::class);

                // Notification au locataire
                $notificationService->notify(
                    $location->bien->proprietaire,
                    'paiement_recu',
                    'Paiement reçu !',
                    "Votre location ({$location->bien->titre}) a été confirmée. Reçu : {$numeroRecu}.",
                    ['location_id' => (string) $location->id]
                );

                // Notification au propriétaire
                $proprietaire = \App\Models\User::find($location->proprietaire_id);
                if ($proprietaire) {
                    $notificationService->notify(
                        $proprietaire,
                        'bien_loue',
                        'Votre bien a été loué !',
                        "Votre bien \"{$location->bien->titre}\" a été loué. Le reversement de " . number_format((float)$location->prix_proprietaire * $location->duree_mois, 0, ',', ' ') . " FCFA est en cours de traitement.",
                        ['location_id' => (string) $location->id]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("Erreur notification paiement: " . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement confirmé ! Votre location est maintenant active.',
                'data'    => [
                    'location_id' => $location->id,
                    'statut'      => 'actif',
                    'recu'        => [
                        'id'          => $recu->id,
                        'numero_recu' => $recu->numero_recu,
                        'date'        => $recu->date_emission->toIso8601String(),
                    ],
                    'dates'       => [
                        'debut' => $location->date_debut->toDateString(),
                        'fin'   => $location->date_fin->toDateString(),
                    ],
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation du paiement.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/mobile/locations/{id}/contrat/telecharger
    // Télécharger le PDF du contrat (locataire ou propriétaire uniquement)
    // ─────────────────────────────────────────────────────────────────────────

    public function telechargerContrat(Request $request, string $id): mixed
    {
        $location = Location::with('contrat')->findOrFail($id);
        $userId   = $request->user()->id;

        // Seuls le locataire et le propriétaire peuvent télécharger
        if ($userId !== $location->locataire_id && $userId !== $location->proprietaire_id) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $contrat = $location->contrat;
        if (! $contrat) {
            return response()->json(['success' => false, 'message' => 'Contrat introuvable.'], 404);
        }

        // Si le PDF existe déjà sur le disque, le télécharger
        if ($contrat->fichier_pdf && Storage::disk('local')->exists($contrat->fichier_pdf)) {
            return Storage::disk('local')->download(
                $contrat->fichier_pdf,
                "Contrat-ImmoPro-{$location->id}.pdf"
            );
        }

        // Générer le vrai PDF binaire avec DomPDF
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdfContent = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($contrat->contenu_html)->output();
            
            $annee  = now()->year;
            $chemin = "contrats/{$annee}/CTR-{$location->id}.pdf";
            Storage::disk('local')->put($chemin, $pdfContent);
            $contrat->update(['fichier_pdf' => $chemin]);

            return response($pdfContent, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"Contrat-ImmoPro-{$location->id}.pdf\"",
            ]);
        }

        // Fallback HTML si DomPDF n'est pas actif
        return response($contrat->contenu_html, 200, [
            'Content-Type'        => 'text/html; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"Contrat-ImmoPro-{$location->id}.html\"",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/mobile/locations/{id}/recu/telecharger
    // Télécharger le PDF du reçu (locataire uniquement)
    // ─────────────────────────────────────────────────────────────────────────

    public function telechargerRecu(Request $request, string $id): mixed
    {
        $location = Location::with(['paiement.recu'])->findOrFail($id);

        if ($request->user()->id !== $location->locataire_id) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $recu = optional($location->paiement)->recu;
        if (! $recu) {
            return response()->json(['success' => false, 'message' => 'Reçu introuvable.'], 404);
        }

        if ($recu->fichier_pdf && Storage::disk('local')->exists($recu->fichier_pdf)) {
            return Storage::disk('local')->download(
                $recu->fichier_pdf,
                "Recu-{$recu->numero_recu}.pdf"
            );
        }

        return response()->json([
            'success'    => true,
            'numero_recu'=> $recu->numero_recu,
            'message'    => 'PDF non disponible.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    private function genererContenuContrat(Location $location, Bien $bien, $locataire): string
    {
        $proprio = \App\Models\User::find($location->proprietaire_id);
        $dateDebut = \Carbon\Carbon::parse($location->date_debut)->format('d/m/Y');
        $loyerMensuelPublic = number_format((float) $bien->prix_public, 0, ',', ' ') . ' FCFA';
        $totalLocation = number_format((float) $location->montant_total, 0, ',', ' ') . ' FCFA';

        // Sélection dynamique du modèle de contrat adapté à la catégorie du bien (Habitation, Commercial, Meublé)
        $template = $this->trouverModeleContratPourBien($bien);

        $html = $template ? $template->contenu_html : '';

        $nomProprio = trim(($proprio?->first_name ?? '') . ' ' . ($proprio?->last_name ?? ''));
        if (empty($nomProprio)) {
            $nomProprio = $proprio?->name ?? 'Propriétaire ImmoPro';
        }

        $nomLocataire = trim(($locataire->first_name ?? '') . ' ' . ($locataire->last_name ?? ''));
        if (empty($nomLocataire)) {
            $nomLocataire = $locataire->name ?? 'Locataire';
        }

        $replacements = [
            '{NOM_LOCATAIRE}'     => $nomLocataire,
            '{TEL_LOCATAIRE}'     => $locataire->telephone ?? 'Non renseigné',
            '{EMAIL_LOCATAIRE}'   => $locataire->email ?? 'Non renseigné',
            '{NOM_PROPRIETAIRE}'   => $nomProprio,
            '{TEL_PROPRIETAIRE}'   => $proprio?->telephone ?? 'Non renseigné',
            '{TITRE_BIEN}'        => $bien->titre ?? 'Bien Immobilier',
            '{ADRESSE_BIEN}'      => $bien->adresse ?? 'Lomé',
            '{TYPE_BIEN}'         => ucfirst($bien->type_bien ?? 'Habitation'),
            '{LOYER_MENSUEL}'     => $loyerMensuelPublic,
            '{DATE_DEBUT}'        => $dateDebut,
            '{DUREE_MOIS}'        => $location->duree_mois,
            '{TOTAL_LOCATION}'    => $totalLocation,
        ];

        foreach ($replacements as $key => $val) {
            $html = str_replace($key, (string) $val, $html);
        }

        return $html;
    }

    /**
     * Recherche dynamiquement le modèle de contrat correspondant au type et à la catégorie du bien.
     */
    private function trouverModeleContratPourBien(Bien $bien): ?\App\Models\ContratTemplate
    {
        $typeBien = strtolower($bien->type_bien ?? '');
        $caracteristiques = (array) ($bien->caracteristiques ?? []);
        $estMeuble = !empty($caracteristiques['meuble']) || !empty($caracteristiques['est_meuble']) || $typeBien === 'meuble';

        // 1. Si le bien est meublé ou courte durée
        if ($estMeuble) {
            $template = \App\Models\ContratTemplate::where('type', 'meuble')->where('est_actif', true)->first();
            if ($template) return $template;
        }

        // 2. Si le bien est un local commercial / bureau / boutique
        if (in_array($typeBien, ['bureau_commerce', 'bureau', 'commerce', 'magasin', 'commercial'])) {
            $template = \App\Models\ContratTemplate::where('type', 'commercial')->where('est_actif', true)->first();
            if ($template) return $template;
        }

        // 3. Si le bien est à usage d'habitation (maison, villa, appartement, studio, etc.)
        if (in_array($typeBien, ['habitation', 'maison', 'villa', 'appartement', 'chambre_studio', 'duplex'])) {
            $template = \App\Models\ContratTemplate::where('type', 'habitation')->where('est_actif', true)->first();
            if ($template) return $template;
        }

        // 4. Recherche par correspondance exacte du type/slug
        $templateDirect = \App\Models\ContratTemplate::where('type', $typeBien)->where('est_actif', true)->first();
        if ($templateDirect) return $templateDirect;

        // 5. Fallback : Modèle par défaut ou premier modèle actif disponible
        $fallback = \App\Models\ContratTemplate::where('est_defaut', true)->first()
            ?? \App\Models\ContratTemplate::where('est_actif', true)->first();

        if (! $fallback) {
            (new \Database\Seeders\ContratTemplateSeeder())->run();
            $fallback = \App\Models\ContratTemplate::where('est_defaut', true)->first()
                ?? \App\Models\ContratTemplate::first();
        }

        return $fallback;
    }

    private function genererPdfContrat(Contrat $contrat, Location $location): ?string
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return null;
        }

        $pdf    = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($contrat->contenu_html);
        $annee  = now()->year;
        $chemin = "contrats/{$annee}/CTR-{$location->id}.pdf";

        Storage::disk('local')->put($chemin, $pdf->output());
        return $chemin;
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
        <p><strong>Bien :</strong> {$location->bien->adresse}</p>
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

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/mobile/historique-paiements
    // Historique des paiements du client connecté
    // ─────────────────────────────────────────────────────────────────────────
    public function historiquePaiements(Request $request): JsonResponse
    {
        $user = $request->user();

        $locations = \App\Models\Location::with(['bien', 'paiement', 'recu'])
            ->where('client_id', $user->id)
            ->latest()
            ->paginate(20);

        $data = $locations->map(function ($loc) {
            $bien = $loc->bien;
            return [
                'location_id'   => $loc->id,
                'statut'        => $loc->statut,
                'date_debut'    => $loc->date_debut,
                'date_fin'      => $loc->date_fin,
                'duree_mois'    => $loc->duree_mois,
                'montant_total' => $loc->montant_total,
                'created_at'    => $loc->created_at,
                'bien' => $bien ? [
                    'id'      => $bien->id,
                    'titre'   => $bien->titre,
                    'adresse' => $bien->adresse,
                    'ville'   => $bien->ville,
                    'image'   => $bien->medias()->where('type', 'image')->orderBy('ordre')->first()?->url,
                ] : null,
                'paiement' => $loc->paiement ? [
                    'id'                    => $loc->paiement->id,
                    'montant'               => $loc->paiement->montant,
                    'statut'                => $loc->paiement->statut,
                    'operateur_paiement'    => $loc->paiement->operateur_paiement,
                    'reference_transaction' => $loc->paiement->reference_transaction,
                    'created_at'            => $loc->paiement->created_at,
                ] : null,
                'recu' => $loc->recu ? [
                    'id'          => $loc->recu->id,
                    'numero_recu' => $loc->recu->numero_recu,
                ] : null,
            ];
        });

        return response()->json([
            'success'      => true,
            'data'         => $data,
            'current_page' => $locations->currentPage(),
            'last_page'    => $locations->lastPage(),
            'total'        => $locations->total(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/mobile/statistiques
    // Statistiques du tableau de bord client
    // ─────────────────────────────────────────────────────────────────────────
    public function statistiques(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalLocations    = \App\Models\Location::where('client_id', $user->id)->count();
        $locationsActives  = \App\Models\Location::where('client_id', $user->id)->where('statut', 'actif')->count();
        $totalDepenses     = \App\Models\Location::where('client_id', $user->id)
                                ->whereHas('paiement', fn($q) => $q->where('statut', 'valide'))
                                ->sum('montant_total');
        $totalFavoris      = $user->favoris()->count();
        $totalBiensPublies = \App\Models\Bien::where('user_id', $user->id)->where('statut', 'publie')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_locations'    => $totalLocations,
                'locations_actives'  => $locationsActives,
                'total_depenses'     => (float) $totalDepenses,
                'total_favoris'      => $totalFavoris,
                'total_biens_publies' => $totalBiensPublies,
            ],
        ]);
    }
}
