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
                'fichier_pdf'      => null, // Sera généré si dompdf est installé
                'date_generation'  => now(),
                'statut_signature' => 'en_attente',
            ]);

            // Tenter de générer le PDF si dompdf est disponible
            try {
                $cheminPdf = $this->genererPdfContrat($contrat, $location);
                if ($cheminPdf) {
                    $contrat->update(['fichier_pdf' => $cheminPdf]);
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
                        'id'          => $contrat->id,
                        'contenu_html'=> $contrat->contenu_html,
                        'statut'      => $contrat->statut_signature,
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
    // POST /api/mobile/locations/{id}/payer
    // Initie le paiement (simulation — sans API externe pour l'instant)
    // ─────────────────────────────────────────────────────────────────────────

    public function payer(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'operateur_paiement'    => 'required|string|max:100',
            'reference_transaction' => 'nullable|string|max:255',
        ]);

        $location = Location::where('locataire_id', $request->user()->id)->findOrFail($id);

        if ($location->statut !== 'en_attente_paiement') {
            return response()->json([
                'success' => false,
                'message' => 'Cette location n\'est pas en attente de paiement.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $paiement = Paiement::create([
                'location_id'           => $location->id,
                'montant'               => $location->montant_total,
                'operateur_paiement'    => $request->operateur_paiement,
                'reference_transaction' => $request->reference_transaction,
                'statut'                => 'initie',
            ]);

            DB::commit();

            // NOTE : En production, ici on appellerait l'API de l'opérateur
            // et on retournerait une URL de redirection ou un code USSD.
            // Pour l'instant on retourne le paiement initié (simulation).
            return response()->json([
                'success'    => true,
                'message'    => 'Paiement initié. Confirmez votre paiement via ' . $request->operateur_paiement . '.',
                'data'       => [
                    'paiement_id'        => $paiement->id,
                    'montant'            => (float) $paiement->montant,
                    'operateur'          => $paiement->operateur_paiement,
                    'statut'             => $paiement->statut,
                    'instructions'       => "Composez le code de paiement {$request->operateur_paiement} pour valider.",
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initiation du paiement.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
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

        if ($paiement->statut !== 'initie') {
            return response()->json([
                'success' => false,
                'message' => 'Ce paiement a déjà été traité.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Récupérer le pourcentage de commission appliqué
            $categorie            = $location->bien->getCategorie();
            $pourcentageApplique  = $categorie ? (float) $categorie->pourcentage_commission : 0;

            // 1. Valider le paiement
            $paiement->update([
                'statut'                => 'succes',
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

        // Si le PDF existe, on le sert depuis le stockage privé
        if ($contrat->fichier_pdf && Storage::disk('local')->exists($contrat->fichier_pdf)) {
            return Storage::disk('local')->download(
                $contrat->fichier_pdf,
                "Contrat-{$location->id}.pdf"
            );
        }

        // Fallback : retourner le contenu HTML si le PDF n'est pas disponible
        return response()->json([
            'success'      => true,
            'contenu_html' => $contrat->contenu_html,
            'message'      => 'PDF non disponible. Contenu HTML retourné.',
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
        $dateDebut = $location->date_debut->format('d/m/Y');
        $dateFin   = $location->date_fin->format('d/m/Y');
        $montant   = number_format((float) $location->montant_total / $location->duree_mois, 0, ',', ' ');

        return "
        <h1>CONTRAT DE LOCATION</h1>
        <p><strong>Entre les soussignés :</strong></p>
        <p><strong>BAILLEUR :</strong> {$proprio?->first_name} {$proprio?->last_name}</p>
        <p><strong>LOCATAIRE :</strong> {$locataire->first_name} {$locataire->last_name}</p>
        <hr>
        <h2>BIEN LOUÉ</h2>
        <p><strong>Adresse :</strong> {$bien->adresse}</p>
        <p><strong>Type :</strong> {$bien->type_bien}</p>
        <h2>CONDITIONS DE LOCATION</h2>
        <p><strong>Durée :</strong> {$location->duree_mois} mois</p>
        <p><strong>Du :</strong> {$dateDebut} <strong>Au :</strong> {$dateFin}</p>
        <p><strong>Loyer mensuel :</strong> {$montant} FCFA</p>
        <p><strong>Montant total :</strong> " . number_format((float) $location->montant_total, 0, ',', ' ') . " FCFA</p>
        <hr>
        <p>Le présent contrat est soumis aux conditions générales de la plateforme ImmoPro.</p>
        ";
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
}
