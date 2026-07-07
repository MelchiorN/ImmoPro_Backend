<?php

namespace App\Http\Controllers\Bien;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBienRequest;
use App\Http\Requests\UpdateBienRequest;
use App\Http\Resources\BienListResource;
use App\Http\Resources\BienResource;
use App\Models\Bien;
use App\Models\DocumentBien;
use App\Models\MediaBien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BienController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/mes-biens
    // Liste des biens du propriétaire connecté
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
    // Créer et soumettre un bien (multipart/form-data)
    // ─────────────────────────────────────────────────────────────────────────

    public function store(StoreBienRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // 1. Créer le bien
            $bien = Bien::create([
                'user_id'          => $request->user()->id,
                'type_bien'        => $request->input('type_bien'),
                'type_transaction' => $request->input('type_transaction'),
                'titre'            => $request->input('titre'),
                'description'      => $request->input('description'),
                'prix'             => $request->input('prix'),
                'surface'          => $request->input('surface'),
                'nb_pieces'        => $request->input('nb_pieces'),
                'nb_salles_bain'   => $request->input('nb_salles_bain'),
                'adresse'          => $request->input('adresse'),
                'latitude'         => $request->input('latitude'),
                'longitude'        => $request->input('longitude'),
                'statut'           => 'en_attente',
            ]);

            // 2. Sauvegarder les médias
            if ($request->hasFile('medias')) {
                foreach ($request->file('medias') as $index => $fichier) {
                    $ext      = $fichier->getClientOriginalExtension();
                    $mime     = $fichier->getMimeType();
                    $isVideo  = str_starts_with($mime, 'video/');
                    $dossier  = "biens/{$bien->id}/medias";
                    $chemin   = $fichier->store($dossier, 'public');

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

            // 3. Sauvegarder les documents
            $typesDocuments = [
                'titre_foncier'  => 'titre_foncier',
                'piece_identite' => 'piece_identite',
                'plan_cadastral' => 'plan_cadastral',
            ];

            foreach ($typesDocuments as $inputKey => $typeDoc) {
                if ($request->hasFile("documents.{$inputKey}")) {
                    $fichier = $request->file("documents.{$inputKey}");
                    $dossier = "biens/{$bien->id}/documents";
                    $chemin  = $fichier->store($dossier, 'local'); // privé

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

            DB::commit();

            // Charger les relations pour la réponse
            $bien->load(['medias', 'documents']);

            return response()->json([
                'success' => true,
                'message' => 'Votre annonce a été soumise pour vérification. Délai : 24-48h.',
                'data'    => new BienResource($bien),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            // Nettoyer les fichiers éventuellement uploadés
            if (isset($bien)) {
                Storage::disk('public')->deleteDirectory("biens/{$bien->id}/medias");
                Storage::disk('local')->deleteDirectory("biens/{$bien->id}/documents");
            }

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la soumission.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/mes-biens/{bien}
    // Détail d'un bien du propriétaire connecté
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
    // Modifier un bien (brouillon ou rejeté seulement)
    // ─────────────────────────────────────────────────────────────────────────

    public function update(UpdateBienRequest $request, Bien $bien): JsonResponse
    {
        $bien->update($request->validated());

        // Si modifié après rejet → repasse en attente
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
    // DELETE /api/mes-biens/{bien}
    // Supprimer (soft-delete) un bien
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(Request $request, string $id): JsonResponse
    {
        $bien = Bien::where('user_id', $request->user()->id)->findOrFail($id);

        // On empêche la suppression d'un bien publié
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
}
