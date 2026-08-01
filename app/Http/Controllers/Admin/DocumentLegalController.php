<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentLegal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DocumentLegalController
 *
 * Gère les documents légaux depuis l'interface d'administration.
 * Les données sont accessibles publiquement (sans auth) en lecture
 * pour l'affichage sur le mobile.
 */
class DocumentLegalController extends Controller
{
    // ── Lecture publique (mobile + web sans auth) ─────────────────────────────

    /**
     * GET /api/legal
     * Retourne tous les documents actifs (liste allégée pour le mobile).
     */
    public function indexPublic(): JsonResponse
    {
        $documents = DocumentLegal::actifs()
            ->orderBy('id')
            ->get(['slug', 'titre', 'description', 'version', 'date_maj']);

        return response()->json([
            'success' => true,
            'data'    => $documents,
        ]);
    }

    /**
     * GET /api/legal/{slug}
     * Retourne un document légal complet par son slug.
     */
    public function showPublic(string $slug): JsonResponse
    {
        $doc = DocumentLegal::where('slug', $slug)->where('actif', true)->first();

        if (! $doc) {
            return response()->json([
                'success' => false,
                'message' => 'Document non trouvé.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $doc,
        ]);
    }

    // ── Administration (protégé role:admin) ───────────────────────────────────

    /**
     * GET /api/admin/legal
     * Liste tous les documents (actifs et inactifs) pour l'admin.
     */
    public function index(): JsonResponse
    {
        $documents = DocumentLegal::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => $documents,
        ]);
    }

    /**
     * GET /api/admin/legal/{slug}
     * Retourne un document complet (admin — tous statuts).
     */
    public function show(string $slug): JsonResponse
    {
        $doc = DocumentLegal::where('slug', $slug)->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $doc,
        ]);
    }

    /**
     * PUT /api/admin/legal/{slug}
     * Met à jour le contenu d'un document légal.
     * Si le slug n'existe pas encore, crée le document.
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'titre'       => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'contenu'     => 'required|string',
            'actif'       => 'sometimes|boolean',
        ]);

        $doc = DocumentLegal::firstOrNew(['slug' => $slug]);

        // Initialisation si nouveau document
        if (! $doc->exists) {
            $doc->slug    = $slug;
            $doc->version = 1;
        }

        $doc->fill($validated);

        // Incrémenter la version et mettre à jour la date éditoriale
        if ($doc->exists) {
            $doc->version = ($doc->version ?? 1) + 1;
        }
        $doc->date_maj = now();
        $doc->save();

        activity()
            ->causedBy($request->user())
            ->performedOn($doc)
            ->withProperties(['slug' => $slug, 'version' => $doc->version])
            ->log("Document légal mis à jour : {$doc->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Document mis à jour avec succès.',
            'data'    => $doc,
        ]);
    }

    /**
     * PATCH /api/admin/legal/{slug}/toggle
     * Active ou désactive un document légal.
     */
    public function toggle(Request $request, string $slug): JsonResponse
    {
        $doc = DocumentLegal::where('slug', $slug)->firstOrFail();
        $doc->update(['actif' => ! $doc->actif]);

        activity()
            ->causedBy($request->user())
            ->performedOn($doc)
            ->withProperties(['actif' => $doc->actif])
            ->log("Document légal " . ($doc->actif ? 'activé' : 'désactivé') . " : {$doc->titre}");

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour.',
            'data'    => ['slug' => $doc->slug, 'actif' => $doc->actif],
        ]);
    }
}
