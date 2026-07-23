<?php

namespace App\Http\Controllers;

use App\Models\Paiement;
use App\Models\Location;
use App\Models\Recu;
use App\Models\Commission;
use App\Models\Reversement;
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

        $paiement = Paiement::with(['location.bien', 'location.locataire', 'location.proprietaire'])
            ->find($paiementId);

        if (! $paiement) {
            Log::warning('[Semoa Webhook] Paiement introuvable', ['paiement_id' => $paiementId]);
            return response()->json(['success' => false, 'message' => 'Paiement introuvable.'], 404);
        }

        // Ignorer si le paiement a déjà été traité
        if ($paiement->statut === 'confirme') {
            return response()->json(['success' => true, 'message' => 'Déjà traité.']);
        }

        $status = strtoupper($payload['state'] ?? $payload['status'] ?? '');

        // ── Paiement Réussi (PAID) ─────────────────────────────────────
        if ($status === 'PAID') {
            return $this->confirmerPaiement($paiement, $payload);
        }

        // ── Paiement Échoué ou Annulé ──────────────────────────────────
        if (in_array($status, ['CANCELLED', 'FAILED', 'EXPIRED', 'ERROR'])) {
            $paiement->update([
                'statut'                => 'echoue',
                'reference_transaction' => $payload['order_reference'] ?? $payload['transaction_id'] ?? $paiement->reference_transaction,
            ]);

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
}
