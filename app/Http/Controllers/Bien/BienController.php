<?php

namespace App\Http\Controllers\Bien;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBienRequest;
use App\Http\Requests\UpdateBienRequest;
use App\Http\Resources\BienListResource;
use App\Http\Resources\BienResource;
use App\Models\Bien;
use App\Models\Categorie;
use App\Models\ConfigPublication;
use App\Models\DocumentBien;
use App\Models\MediaBien;
use App\Models\Paiement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BienController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/mes-biens
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $biens = Bien::with(['medias'])
            ->where('user_id', $request->user()->id)
            ->when($request->query('statut'), fn ($q, $s) => $q->where('statut', $s))
            ->latest()
            ->paginate($request->query('per_page', 15));

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
    // POST /api/biens
    // Étape 1 : Créer le dossier. Si frais d'étude actifs → retourner le
    // montant à payer SANS créer le bien. Le bien est créé après paiement
    // confirmé via POST /api/client/frais-etude/confirmer.
    // Si frais désactivés → créer directement.
    // ─────────────────────────────────────────────────────────────────────────

    public function store(StoreBienRequest $request): JsonResponse
    {
        $user   = $request->user();
        $config = ConfigPublication::instance();

        // ── 1. Vérifier quota de publication ─────────────────────────────────
        if (! $user->peutPublier()) {
            return response()->json([
                'success' => false,
                'code'    => 'QUOTA_EPUISE',
                'message' => 'Vous n\'avez plus de publications disponibles. Souscrivez à un abonnement.',
                'data'    => [
                    'essais_gratuits_restants' => $user->essais_gratuits_restants,
                    'abonnement_actif'         => null,
                ],
            ], 403);
        }

        // ── 2. Calculer frais d'étude si activés ──────────────────────────────
        if ($config->frais_etude_actifs) {
            $categorie = Categorie::where('slug', $request->input('type_bien'))->first();
            $frais     = $categorie ? $categorie->calculerFraisEtude((float) $request->input('prix')) : 0;

            if ($frais > 0) {
                // Stocker le payload dans un paiement "initié" temporaire
                // sans créer le bien — on retourne les infos pour que le client paie
                return response()->json([
                    'success'       => false,
                    'code'          => 'FRAIS_ETUDE_REQUIS',
                    'message'       => 'Des frais d\'étude de dossier sont requis avant la soumission.',
                    'data'          => [
                        'montant_frais'     => $frais,
                        'categorie'         => $categorie->nom,
                        'pourcentage'       => (float) $categorie->frais_etude_pourcentage,
                        'prix_bien'         => (float) $request->input('prix'),
                        'instructions'      => "Payez {$frais} FCFA pour soumettre votre dossier via POST /api/client/frais-etude/initier",
                    ],
                ], 402);
            }
        }

        // ── 3. Créer le bien directement (frais désactivés ou = 0) ────────────
        return $this->creerBien($request, $user, 'non_requis');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Méthode interne partagée avec FraisEtudeController
    // ─────────────────────────────────────────────────────────────────────────

    public function creerBien(Request $request, $user, string $fraisStatut = 'non_requis', ?string $fraisPaiementId = null): JsonResponse
    {
        DB::beginTransaction();
        try {
            $typeBien  = $request->input('type_bien');
            $categorie = Categorie::where('slug', $typeBien)->first();

            $bien = Bien::create([
                'user_id'               => $user->id,
                'type_bien'             => $typeBien,
                'type_transaction'      => $request->input('type_transaction'),
                'titre'                 => $request->input('titre'),
                'description'           => $request->input('description'),
                'prix'                  => $request->input('prix'),
                'prix_public'           => $categorie
                                            ? $categorie->calculerPrixPublic((float) $request->input('prix'))
                                            : $request->input('prix'),
                'unite_prix'            => $request->input('unite_prix'),
                'avance_mois'           => $request->input('avance_mois'),
                'caution'               => $request->input('caution'),
                'surface'               => $request->input('surface'),
                'superficie'            => $request->input('superficie'),
                'nb_pieces'             => $request->input('nb_pieces'),
                'nb_salles_bain'        => $request->input('nb_salles_bain'),
                'caracteristiques'      => $request->input('caracteristiques'),
                'adresse'               => $request->input('adresse'),
                'latitude'              => $request->input('latitude'),
                'longitude'             => $request->input('longitude'),
                'statut'                => 'en_attente',
                // Déposant
                'role_deposant'         => $request->input('role_deposant', 'proprietaire'),
                'proprietaire_nom'      => $request->input('proprietaire_nom'),
                'proprietaire_prenom'   => $request->input('proprietaire_prenom'),
                'proprietaire_sexe'     => $request->input('proprietaire_sexe'),
                'proprietaire_nationalite' => $request->input('proprietaire_nationalite'),
                'proprietaire_telephone' => $request->input('proprietaire_telephone'),
                'proprietaire_email'    => $request->input('proprietaire_email'),
                'proprietaire_adresse'  => $request->input('proprietaire_adresse'),
                // Frais d'étude
                'frais_etude_statut'         => $fraisStatut,
                'frais_etude_paiement_id'    => $fraisPaiementId,
            ]);

            // ── Médias ────────────────────────────────────────────────────────
            if ($request->hasFile('medias')) {
                foreach ($request->file('medias') as $index => $fichier) {
                    $mime    = $fichier->getMimeType();
                    $isVideo = str_starts_with($mime, 'video/');
                    $dossier = "biens/{$bien->id}/medias";
                    $chemin  = $fichier->store($dossier, 'public');

                    MediaBien::create([
                        'bien_id'        => $bien->id,
                        'type'           => $isVideo ? 'video' : 'photo',
                        'chemin'         => $chemin,
                        'url'            => Storage::disk('public')->url($chemin),
                        'est_principale' => $index === 0,
                        'ordre'          => $index,
                        'taille'         => $fichier->getSize(),
                        'mime_type'      => $mime,
                    ]);
                }
            }

            // ── Documents ─────────────────────────────────────────────────────
            $mappingDocuments = [
                'justificatif_propriete'    => 'justificatif_propriete',
                'piece_identite'            => 'piece_identite',
                'piece_identite_deposant'   => 'piece_identite_deposant',
                'mandat_gestion'            => 'mandat_gestion',
                'procuration'               => 'procuration',
                'acte_succession'           => 'acte_succession',
                'autorisation_ecrite'       => 'autorisation_ecrite',
            ];

            foreach ($mappingDocuments as $inputKey => $typeDoc) {
                if ($request->hasFile("documents.{$inputKey}")) {
                    $fichier = $request->file("documents.{$inputKey}");
                    $dossier = "biens/{$bien->id}/documents";
                    $chemin  = $fichier->store($dossier, 'local');

                    DocumentBien::create([
                        'bien_id'      => $bien->id,
                        'type'         => $typeDoc,
                        'chemin'       => $chemin,
                        'nom_original' => $fichier->getClientOriginalName(),
                        'taille'       => $fichier->getSize(),
                        'mime_type'    => $fichier->getMimeType(),
                        'statut'       => 'en_attente',
                    ]);
                }
            }

            // ── Décrémenter le quota ──────────────────────────────────────────
            $abonnement = $user->abonnementActif();
            if ($abonnement) {
                $abonnement->consommerUnePublication();
            } elseif ($user->essais_gratuits_restants > 0) {
                $user->decrement('essais_gratuits_restants');
            }

            DB::commit();

            // ── Notifier admins/agents ────────────────────────────────────────
            try {
                $notif    = app(\App\Services\NotificationService::class);
                $staffs   = \App\Models\User::whereIn('role', ['admin', 'agent'])->get();
                foreach ($staffs as $staff) {
                    $notif->notify(
                        $staff,
                        'nouveau_bien',
                        'Nouveau bien à vérifier',
                        "Un nouveau bien ({$bien->titre}) a été soumis et est en attente de vérification.",
                        ['bien_id' => (string) $bien->id]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Erreur notification nouveau bien: ' . $e->getMessage());
            }

            $bien->load(['medias', 'documents']);

            return response()->json([
                'success' => true,
                'message' => 'Votre dossier a été soumis avec succès. Délai de vérification : 24-48h.',
                'data'    => new BienResource($bien),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            if (isset($bien)) {
                Storage::disk('public')->deleteDirectory("biens/{$bien->id}/medias");
                Storage::disk('local')->deleteDirectory("biens/{$bien->id}/documents");
            }
            Log::error('Erreur création bien: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la soumission.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/mes-biens/{id}
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Request $request, string $id): JsonResponse
    {
        $bien = Bien::with(['medias', 'documents'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => new BienResource($bien),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/mes-biens/{bien}
    // ─────────────────────────────────────────────────────────────────────────

    public function update(UpdateBienRequest $request, Bien $bien): JsonResponse
    {
        if (in_array($bien->statut, ['valide', 'publie', 'archive'])) {
            return response()->json([
                'success' => false,
                'message' => 'Une annonce validée, publiée ou archivée ne peut plus être modifiée.',
            ], 422);
        }

        $bien->update($request->validated());

        if ($request->has('prix') || $request->has('type_bien')) {
            $categorie = $bien->getCategorie();
            $bien->update([
                'prix_public' => $categorie
                    ? $categorie->calculerPrixPublic((float) $bien->prix)
                    : $bien->prix,
            ]);
        }

        if ($bien->statut === 'rejete') {
            $bien->update(['statut' => 'en_attente', 'note_admin' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bien mis à jour.',
            'data'    => new BienResource($bien->fresh(['medias', 'documents'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE /api/mes-biens/{id}
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(Request $request, string $id): JsonResponse
    {
        $bien = Bien::where('user_id', $request->user()->id)->findOrFail($id);

        if ($bien->statut === 'publie') {
            return response()->json([
                'success' => false,
                'message' => 'Un bien publié ne peut pas être supprimé. Archivez-le d\'abord.',
            ], 422);
        }

        $bien->delete();

        return response()->json([
            'success' => true,
            'message' => 'Annonce supprimée.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/mes-biens/{id}/media
    // ─────────────────────────────────────────────────────────────────────────

    public function updateMedia(Request $request, string $id): JsonResponse
    {
        $bien = Bien::where('user_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'medias'   => 'required|array|min:1',
            'medias.*' => 'required|file|image|max:10240',
        ]);

        DB::beginTransaction();
        try {
            $anciens = MediaBien::where('bien_id', $bien->id)->get();
            foreach ($anciens as $am) {
                Storage::disk('public')->delete($am->chemin);
                $am->delete();
            }

            foreach ($request->file('medias') as $index => $fichier) {
                $mime    = $fichier->getMimeType();
                $dossier = "biens/{$bien->id}/medias";
                $chemin  = $fichier->store($dossier, 'public');

                MediaBien::create([
                    'bien_id'        => $bien->id,
                    'type'           => 'photo',
                    'chemin'         => $chemin,
                    'url'            => Storage::disk('public')->url($chemin),
                    'est_principale' => $index === 0,
                    'ordre'          => $index,
                    'taille'         => $fichier->getSize(),
                    'mime_type'      => $mime,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Images mises à jour.',
                'data'    => new BienResource($bien->fresh(['medias'])),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des images.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }
}
