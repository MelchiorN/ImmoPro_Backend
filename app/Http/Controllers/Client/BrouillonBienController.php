<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Categorie;
use App\Models\DocumentBien;
use App\Models\MediaBien;
use App\Services\NotificationService;
use App\Services\EmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gestion des brouillons de publication de biens.
 *
 * Flux :
 *  1. POST /api/client/biens/brouillon   → crée un bien en statut 'brouillon'
 *  2. PUT  /api/client/biens/{id}/brouillon → met à jour un brouillon existant
 *  3. POST /api/client/biens/{id}/soumettre → soumet le brouillon pour vérification
 *                                             (statut → en_attente, enregistre publication_auto)
 */
class BrouillonBienController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/client/biens/brouillon
    // Crée un nouveau bien en brouillon. Peut être appelé à n'importe quelle
    // étape avec seulement les champs remplis jusqu'alors (tous optionnels).
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $bien = Bien::create(array_filter([
            'user_id'                  => $user->id,
            'statut'                   => 'brouillon',
            'publication_auto'         => $request->boolean('publication_auto', true),
            'type_bien'                => $request->input('type_bien'),
            'type_transaction'         => $request->input('type_transaction', 'location'),
            'titre'                    => $request->input('titre'),
            'description'              => $request->input('description'),
            'prix'                     => $request->input('prix'),
            'unite_prix'               => $request->input('unite_prix', 'mois'),
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
            'role_deposant'            => $request->input('role_deposant', 'proprietaire'),
            'proprietaire_nom'         => $request->input('proprietaire_nom'),
            'proprietaire_prenom'      => $request->input('proprietaire_prenom'),
            'proprietaire_sexe'        => $request->input('proprietaire_sexe'),
            'proprietaire_nationalite' => $request->input('proprietaire_nationalite'),
            'proprietaire_telephone'   => $request->input('proprietaire_telephone'),
            'proprietaire_email'       => $request->input('proprietaire_email'),
            'proprietaire_adresse'     => $request->input('proprietaire_adresse'),
        ], fn ($v) => $v !== null && $v !== ''));

        // Sauvegarder les médias s'il y en a
        $this->saveMedias($request, $bien);

        // Sauvegarder les documents s'il y en a
        $this->saveDocuments($request, $bien);

        return response()->json([
            'success' => true,
            'message' => 'Brouillon enregistré.',
            'data'    => ['id' => $bien->id],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/client/biens/{id}/brouillon
    // Met à jour un brouillon existant. Seuls les champs fournis sont mis à jour.
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $bien = Bien::where('id', $id)
                    ->where('user_id', $user->id)
                    ->where('statut', 'brouillon')
                    ->firstOrFail();

        $updates = array_filter([
            'type_bien'                => $request->input('type_bien'),
            'type_transaction'         => $request->input('type_transaction'),
            'titre'                    => $request->input('titre'),
            'description'              => $request->input('description'),
            'prix'                     => $request->input('prix'),
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
            'role_deposant'            => $request->input('role_deposant'),
            'proprietaire_nom'         => $request->input('proprietaire_nom'),
            'proprietaire_prenom'      => $request->input('proprietaire_prenom'),
            'proprietaire_sexe'        => $request->input('proprietaire_sexe'),
            'proprietaire_nationalite' => $request->input('proprietaire_nationalite'),
            'proprietaire_telephone'   => $request->input('proprietaire_telephone'),
            'proprietaire_email'       => $request->input('proprietaire_email'),
            'proprietaire_adresse'     => $request->input('proprietaire_adresse'),
        ], fn ($v) => $v !== null);

        // publication_auto est un boolean — le traiter séparément
        if ($request->has('publication_auto')) {
            $updates['publication_auto'] = $request->boolean('publication_auto');
        }

        if (!empty($updates)) {
            $bien->update($updates);
        }

        // Sauvegarder les médias s'il y en a (replace=true : remplace pour éviter les doublons)
        $this->saveMedias($request, $bien, replace: true);

        // Sauvegarder les documents s'il y en a (remplacement par type déjà géré dans saveDocuments)
        $this->saveDocuments($request, $bien);

        return response()->json([
            'success' => true,
            'message' => 'Brouillon mis à jour.',
            'data'    => ['id' => $bien->id],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/client/biens/{id}/soumettre
    // Soumet le brouillon pour vérification.
    // Enregistre le choix publication_auto et passe le statut à 'en_attente'.
    // ─────────────────────────────────────────────────────────────────────────

    public function soumettre(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // 1. Validation des paramètres de la requête
        $request->validate([
            'publication_auto' => 'required|boolean',
        ]);

        // 2. Récupération du brouillon
        $bien = Bien::where('id', $id)
                    ->where('user_id', $user->id)
                    ->where('statut', 'brouillon')
                    ->firstOrFail();

        // 3. Validation stricte des champs requis sur le brouillon lui-même
        //    (même si l'UI mobile valide, on re-valide côté serveur pour la sécurité)
        $champsManquants = [];
        if (blank($bien->type_bien))         $champsManquants[] = 'Type de bien';
        if (blank($bien->type_transaction))   $champsManquants[] = 'Type de transaction';
        if (blank($bien->titre))             $champsManquants[] = 'Titre';
        if (is_null($bien->prix))            $champsManquants[] = 'Prix';
        if (blank($bien->adresse))           $champsManquants[] = 'Adresse';
        if (is_null($bien->latitude))        $champsManquants[] = 'Localisation (latitude)';
        if (is_null($bien->longitude))       $champsManquants[] = 'Localisation (longitude)';
        if (blank($bien->role_deposant))     $champsManquants[] = 'Rôle du déposant';

        if (!empty($champsManquants)) {
            return response()->json([
                'success' => false,
                'message' => 'Le brouillon est incomplet. Champs manquants : ' . implode(', ', $champsManquants) . '.',
                'champs_manquants' => $champsManquants,
            ], 422);
        }

        // 4. Soumission
        $bien->update([
            'statut'           => 'en_attente',
            'publication_auto' => $request->boolean('publication_auto'),
            'submitted_at'     => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre bien a été soumis pour vérification.',
            'data'    => ['id' => $bien->id],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE /api/client/biens/{id}/brouillon
    // Supprime définitivement (ou soft-delete) un brouillon.
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $bien = Bien::where('id', $id)
                    ->where('user_id', $user->id)
                    ->where('statut', 'brouillon')
                    ->firstOrFail();

        $bien->delete();

        return response()->json([
            'success' => true,
            'message' => 'Brouillon supprimé avec succès.',
        ]);
    }



    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sauvegarde les médias envoyés.
     * En mode PUT (mise à jour brouillon), supprime les médias existants
     * et les remplace par les nouveaux — évite la duplication.
     * En mode POST (création), ajoute simplement.
     */
    private function saveMedias(Request $request, Bien $bien, bool $replace = false): void
    {
        if (!$request->hasFile('medias') && !$request->hasFile('medias[]')) {
            return;
        }
        $files = $request->file('medias') ?? $request->file('medias[]') ?? [];
        if (!is_array($files)) {
            $files = [$files];
        }
        if (empty($files)) {
            return;
        }

        if ($replace) {
            // Supprimer les anciens fichiers du disque et les entrées en BDD
            $existing = MediaBien::where('bien_id', $bien->id)->get();
            foreach ($existing as $media) {
                if ($media->chemin) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($media->chemin);
                }
            }
            MediaBien::where('bien_id', $bien->id)->delete();
        }

        $currentCount = $replace ? 0 : MediaBien::where('bien_id', $bien->id)->count();
        foreach ($files as $index => $fichier) {
            $mime    = $fichier->getMimeType();
            $isVideo = str_starts_with($mime, 'video/');
            $dossier = "biens/{$bien->id}/medias";
            $chemin  = $fichier->store($dossier, 'public');

            MediaBien::create([
                'bien_id'        => $bien->id,
                'type'           => $isVideo ? 'video' : 'photo',
                'chemin'         => $chemin,
                'url'            => \Illuminate\Support\Facades\Storage::disk('public')->url($chemin),
                'est_principale' => ($currentCount + $index) === 0,
                'ordre'          => $currentCount + $index,
                'taille'         => $fichier->getSize(),
                'mime_type'      => $mime,
            ]);
        }
    }

    private function saveDocuments(Request $request, Bien $bien): void
    {
        $knownSlugs = [
            'piece_identite', 'piece_identite_deposant', 'justificatif_propriete',
            'mandat_gestion', 'procuration', 'acte_succession',
            'autorisation_ecrite', 'plan_cadastral',
        ];

        foreach ($knownSlugs as $slug) {
            if ($request->hasFile("documents.{$slug}")) {
                $fichier = $request->file("documents.{$slug}");
                // Supprimer l'ancien document du même type s'il existe
                DocumentBien::where('bien_id', $bien->id)->where('type', $slug)->delete();

                $dossier = "biens/{$bien->id}/documents";
                $chemin  = $fichier->store($dossier, 'local');

                DocumentBien::create([
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

        // Documents "autres"
        $autresFiles = $request->file('documents.autres') ?? [];
        if (!is_array($autresFiles)) {
            $autresFiles = [$autresFiles];
        }
        foreach ($autresFiles as $fichier) {
            if (!$fichier) continue;
            $dossier = "biens/{$bien->id}/documents";
            $chemin  = $fichier->store($dossier, 'local');
            DocumentBien::create([
                'bien_id'      => $bien->id,
                'type'         => 'autre',
                'chemin'       => $chemin,
                'nom_original' => $fichier->getClientOriginalName(),
                'taille'       => $fichier->getSize(),
                'mime_type'    => $fichier->getMimeType(),
                'statut'       => 'en_attente',
            ]);
        }
    }
}
