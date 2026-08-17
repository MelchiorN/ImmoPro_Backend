<?php

namespace App\Http\Controllers\Agent;

use App\Events\BienStatutChanged;
use App\Events\DossierAssigneEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\BienListResource;
use App\Http\Resources\BienResource;
use App\Models\Bien;
use App\Models\DocumentBien;
use App\Models\User;
use App\Notifications\DossierAssigneAdminNotification;
use App\Notifications\DossierPrisEnChargeNotification;
use App\Services\EmailTemplateService;
use App\Services\NotificationService;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AgentBienController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/biens
    // Pool de biens accessibles à l'agent connecté :
    //   - statut=en_attente  + agent_id IS NULL  → onglet "Non assignés"
    //   - statut=en_cours    + agent_id = moi    → onglet "En cours"
    //   - statut=publie|rejete + agent_id = moi  → onglet "Terminés"
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $agentId   = $request->user()->id;
        $onglet    = $request->query('onglet', 'non_assigne'); // non_assigne | en_cours | termine

        $query = Bien::with(['medias', 'proprietaire', 'rapport'])
            ->when(
                $request->query('type_bien'),
                fn ($q, $t) => $q->where('type_bien', $t)
            )
            ->when(
                $request->query('priorite'),
                fn ($q, $p) => $q->where('priorite', $p)
            )
            ->when(
                $request->query('search'),
                fn ($q, $s) => $q->where(function ($sq) use ($s) {
                    $sq->where('titre', 'like', "%{$s}%")
                       ->orWhere('adresse', 'like', "%{$s}%");
                })
            );

        switch ($onglet) {
            case 'non_assigne':
                // Tous les biens en attente sans agent assigné
                $query->where('statut', 'en_attente')
                      ->whereNull('agent_id');
                break;

            case 'en_cours':
                // Biens pris en charge par cet agent (statut en_cours)
                $query->where('statut', 'en_cours')
                      ->where('agent_id', $agentId);
                break;

            case 'termine':
                // Biens approuvés, publiés ou rejetés par cet agent
                $query->whereIn('statut', ['valide', 'publie', 'rejete', 'archive'])
                      ->where('agent_id', $agentId);
                break;

            default:
                $query->where('statut', 'en_attente')
                      ->whereNull('agent_id');
        }

        $biens = $query->latest()->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => BienListResource::collection($biens->items()),
            'meta'    => [
                'total'        => $biens->total(),
                'per_page'     => $biens->perPage(),
                'current_page' => $biens->currentPage(),
                'last_page'    => $biens->lastPage(),
                'onglet'       => $onglet,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/stats
    // Dashboard : KPIs + biens en cours + visites à venir
    // ─────────────────────────────────────────────────────────────────────────

    public function stats(Request $request): JsonResponse
    {
        $agentId = $request->user()->id;

        // ── Compteurs ─────────────────────────────────────────────────────────
        $nonAssigne  = Bien::where('statut', 'en_attente')->whereNull('agent_id')->count();
        $enCours     = Bien::where('statut', 'en_cours')->where('agent_id', $agentId)->count();
        $publies     = Bien::whereIn('statut', ['valide', 'publie'])->where('agent_id', $agentId)->count();
        $rejetes     = Bien::where('statut', 'rejete')->where('agent_id', $agentId)->count();
        $totalTraite = $publies + $rejetes;
        $tauxValid   = $totalTraite > 0 ? round($publies / $totalTraite * 100) : null;

        // Visites
        $visitesTotal     = \App\Models\Visite::where('agent_id', $agentId)->count();
        $visitesPlanifiees = \App\Models\Visite::where('agent_id', $agentId)
            ->whereIn('statut', ['planifiee', 'confirmee'])
            ->where('date_visite', '>=', now())
            ->count();

        // Prochaine visite
        $prochaineVisite = \App\Models\Visite::with(['bien'])
            ->where('agent_id', $agentId)
            ->whereIn('statut', ['planifiee', 'confirmee'])
            ->where('date_visite', '>=', now())
            ->orderBy('date_visite')
            ->first();

        $prochaineHeure = $prochaineVisite?->date_visite
            ? \Carbon\Carbon::parse($prochaineVisite->date_visite)->format('H\hi')
            : null;

        // Rapports brouillons
        $rapportsBrouillons = \App\Models\Rapport::where('agent_id', $agentId)
            ->where('statut', 'brouillon')
            ->count();

        // ── Biens en cours récents (tableau) ──────────────────────────────────
        $biensEnCours = Bien::with(['medias', 'proprietaire'])
            ->where('statut', 'en_cours')
            ->where('agent_id', $agentId)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($b) => [
                'id'      => $b->id,
                'titre'   => $b->titre,
                'adresse' => $b->adresse,
                'photo'   => $b->medias?->firstWhere('est_principale', true)?->url
                             ?? $b->medias?->first()?->url,
                'client'  => $b->proprietaire ? [
                    'nom'   => trim(($b->proprietaire->first_name ?? '') . ' ' . ($b->proprietaire->last_name ?? '')),
                    'email' => $b->proprietaire->email,
                ] : null,
                'priorite'   => $b->priorite,
                'created_at' => $b->created_at?->toIso8601String(),
            ]);

        // ── Visites à venir (mini-calendrier) ─────────────────────────────────
        $upcomingVisites = \App\Models\Visite::with(['bien'])
            ->where('agent_id', $agentId)
            ->whereIn('statut', ['planifiee', 'confirmee'])
            ->where('date_visite', '>=', now())
            ->orderBy('date_visite')
            ->limit(5)
            ->get()
            ->map(fn ($v) => [
                'id'           => $v->id,
                'date_visite'  => $v->date_visite?->toIso8601String(),
                'statut'       => $v->statut,
                'bien_titre'   => $v->bien?->titre,
                'bien_adresse' => $v->bien?->adresse,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'kpis' => [
                    'non_assigne'        => $nonAssigne,
                    'en_cours'           => $enCours,
                    'publies'            => $publies,
                    'visites_planifiees' => $visitesPlanifiees,
                    'taux_validation'    => $tauxValid,
                    'prochaine_visite'   => $prochaineHeure,
                    'rapports_brouillons'=> $rapportsBrouillons,
                ],
                'biens_en_cours'   => $biensEnCours,
                'upcoming_visites' => $upcomingVisites,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/biens/{id}
    // Détail complet d'un bien (accessible si non-assigné ou assigné à moi)
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->id;

        $bien = Bien::with(['medias', 'documents', 'proprietaire'])
            ->where(function ($q) use ($agentId) {
                // Accessible si non assigné ou assigné à moi
                $q->whereNull('agent_id')
                  ->orWhere('agent_id', $agentId);
            })
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => new BienResource($bien),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/agent/biens/{id}/claim
    // L'agent prend en charge un bien non assigné → statut passe à "en_cours"
    // ─────────────────────────────────────────────────────────────────────────

    public function claim(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->id;

        // Transaction + verrou pour éviter la race condition
        $updated = DB::transaction(function () use ($id, $agentId) {
            // Chercher le bien non-assigné et le verrouiller
            $bien = Bien::where('id', $id)
                        ->where('statut', 'en_attente')
                        ->whereNull('agent_id')
                        ->lockForUpdate()
                        ->first();

            if (! $bien) {
                return null; // déjà pris ou inexistant
            }

            $bien->update([
                'agent_id' => $agentId,
                'statut'   => 'en_cours',
            ]);
            return $bien;
        });

        if (! $updated) {
            return response()->json([
                'success' => false,
                'message' => 'Ce bien est déjà pris en charge par un autre agent, ou n\'existe pas.',
            ], 409); // Conflict
        }

        // ── Enregistrer claimed_at ────────────────────────────────────────────
        $updated->update(['claimed_at' => now()]);

        // ── Notifier et Broadcaster de manière asynchrone (après l'envoi de la réponse HTTP) ──
        $currentUser = $request->user();
        app()->terminating(function () use ($updated, $currentUser) {
            // Notifier le propriétaire du bien
            try {
                $proprietaire = $updated->proprietaire ?? User::find($updated->user_id);
                $agent        = clone $currentUser;

                if ($proprietaire) {
                    $proprietaire->notify(new DossierPrisEnChargeNotification($updated, $agent));
                }
            } catch (\Throwable $e) {
                Log::warning('[AgentBienController] Erreur notification propriétaire claim: ' . $e->getMessage());
            }

            // Notifier les admins
            try {
                $agent  = clone $currentUser;
                $admins = User::where('role', 'admin')->get();

                foreach ($admins as $admin) {
                    $admin->notify(new DossierAssigneAdminNotification($updated, $agent));
                }
            } catch (\Throwable $e) {
                Log::warning('[AgentBienController] Erreur notification admin claim: ' . $e->getMessage());
            }

            // Broadcast temps réel
            try {
                broadcast(new DossierAssigneEvent($updated->fresh(), $currentUser))->toOthers();
                broadcast(new BienStatutChanged($updated->fresh()))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('[AgentBienController] Erreur broadcast claim: ' . $e->getMessage());
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Dossier pris en charge avec succès.',
            'data'    => new BienResource($updated->fresh(['medias', 'documents', 'proprietaire'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/agent/biens/{id}
    // L'agent modifie les informations textuelles du bien
    // ─────────────────────────────────────────────────────────────────────────

    public function updateBien(\App\Http\Requests\AgentUpdateBienRequest $request, string $id): JsonResponse
    {
        $agentId = $request->user()->id;

        $bien = Bien::where('id', $id)
                    ->where('agent_id', $agentId)
                    ->firstOrFail();

        $validated = $request->validated();

        // Fusionner les attributs dynamiques dans caracteristiques
        if (isset($validated['attributs']) && is_array($validated['attributs'])) {
            $existing = $bien->caracteristiques ?? [];
            $validated['caracteristiques'] = array_merge($existing, $validated['attributs']);
            unset($validated['attributs']);
        }

        $bien->update($validated);
        $bien->update(['last_activity_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Les informations du bien ont été mises à jour.',
            'data'    => new BienResource($bien),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/agent/biens/{id}/release  — DÉSACTIVÉ
    // Un agent ne peut plus libérer un bien une fois pris en charge.
    // ─────────────────────────────────────────────────────────────────────────

    public function release(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Un bien pris en charge ne peut plus être libéré. Contactez un administrateur si nécessaire.',
        ], 403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/agent/biens/{id}/statut  — DÉSACTIVÉ
    // L'agent ne peut plus publier/rejeter un bien directement.
    // La publication passe désormais par le circuit : rapport → admin → propriétaire.
    // ─────────────────────────────────────────────────────────────────────────

    public function updateStatut(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Les agents ne peuvent plus modifier le statut d\'un bien directement. Soumettez un rapport à l\'administration pour décision.',
        ], 403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/biens/counts
    // Compteurs par onglet pour les badges de navigation
    // ─────────────────────────────────────────────────────────────────────────

    public function counts(Request $request): JsonResponse
    {
        $agentId = $request->user()->id;

        $nonAssigne = Bien::where('statut', 'en_attente')
                          ->whereNull('agent_id')
                          ->count();

        $enCours = Bien::where('statut', 'en_cours')
                       ->where('agent_id', $agentId)
                       ->count();

        $termine = Bien::whereIn('statut', ['valide', 'publie', 'rejete', 'archive'])
                       ->where('agent_id', $agentId)
                       ->count();

        return response()->json([
            'success' => true,
            'data'    => compact('nonAssigne', 'enCours', 'termine'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/documents/{docId}
    // Téléchargement sécurisé d'un document privé (disk local)
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadDocument(Request $request, string $docId)
    {
        $user = $request->user();

        // Charger le document avec le bien pour vérifier les droits
        $document = DocumentBien::with('bien')->findOrFail($docId);

        // L'agent doit être assigné au bien (ou le bien doit être non assigné) - Sauf s'il est admin
        $bien = $document->bien;
        if ($user->role !== 'admin' && $bien->agent_id !== null && $bien->agent_id !== $user->id) {
            abort(403, 'Accès refusé à ce document.');
        }

        // Vérifier que le fichier existe sur le disk local
        if (! Storage::disk('local')->exists($document->chemin)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('local')->response(
            $document->chemin,
            $document->nom_original,
            ['Content-Type' => $document->mime_type]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/agent/biens/{id}/workflow
    // Suivi de progression — uniquement les biens assignés à l'agent
    // ou non encore assignés (onglet "Non assignés").
    // ─────────────────────────────────────────────────────────────────────────

    public function workflow(Request $request, string $id): JsonResponse
    {
        $agentId = $request->user()->id;

        $bien = Bien::with(['agent', 'rapport'])
            ->where(function ($q) use ($agentId) {
                $q->where('agent_id', $agentId)
                  ->orWhereNull('agent_id');
            })
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => app(WorkflowService::class)->calculer($bien),
        ]);
    }



    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/agent/biens/{id}/medias
    // Ajouter des photos/vidéos à un bien
    // ─────────────────────────────────────────────────────────────────────────

    public function addMedia(Request $request, string $id): JsonResponse
    {
        $bien = Bien::where('id', $id)
            ->where('agent_id', $request->user()->id)
            ->firstOrFail();

        $request->validate([
            'medias'   => 'required|array|min:1',
            'medias.*' => 'required|file|mimes:jpg,jpeg,png,webp,mp4,mov,avi|max:51200',
        ]);

        $added = [];
        foreach ($request->file('medias') as $fichier) {
            $mime    = $fichier->getMimeType();
            $isVideo = str_starts_with($mime, 'video/');
            $dossier = "biens/{$bien->id}/medias";
            $chemin  = $fichier->store($dossier, 'public');
            $ordre   = \App\Models\MediaBien::where('bien_id', $bien->id)->max('ordre') + 1;

            $media = \App\Models\MediaBien::create([
                'bien_id'        => $bien->id,
                'type'           => $isVideo ? 'video' : 'photo',
                'chemin'         => $chemin,
                'est_principale' => false,
                'ordre'          => $ordre,
                'taille'         => $fichier->getSize(),
                'mime_type'      => $mime,
            ]);
            $added[] = ['id' => $media->id, 'url' => $media->url, 'type' => $media->type];
        }

        return response()->json(['success' => true, 'message' => count($added) . ' média(s) ajouté(s).', 'data' => $added], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE /api/agent/biens/{id}/medias/{mediaId}
    // Supprimer un média d'un bien
    // ─────────────────────────────────────────────────────────────────────────

    public function deleteMedia(Request $request, string $id, string $mediaId): JsonResponse
    {
        $bien = Bien::where('id', $id)->where('agent_id', $request->user()->id)->firstOrFail();

        $media = \App\Models\MediaBien::where('id', $mediaId)->where('bien_id', $bien->id)->firstOrFail();

        Storage::disk('public')->delete($media->chemin);
        $media->delete();

        return response()->json(['success' => true, 'message' => 'Média supprimé.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/agent/biens/{id}/medias/{mediaId}
    // Mettre à jour un média (ex : définir comme principale)
    // ─────────────────────────────────────────────────────────────────────────

    public function updateMedia(Request $request, string $id, string $mediaId): JsonResponse
    {
        $bien = Bien::where('id', $id)->where('agent_id', $request->user()->id)->firstOrFail();

        $media = \App\Models\MediaBien::where('id', $mediaId)->where('bien_id', $bien->id)->firstOrFail();

        if ($request->boolean('est_principale')) {
            // Retirer est_principale de tous les médias du bien
            \App\Models\MediaBien::where('bien_id', $bien->id)->update(['est_principale' => false]);
            $media->update(['est_principale' => true]);
        }

        return response()->json(['success' => true, 'message' => 'Média mis à jour.', 'data' => $media->fresh()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/agent/biens/{id}/documents
    // Ajouter un document à un bien
    // ─────────────────────────────────────────────────────────────────────────

    public function addDocument(Request $request, string $id): JsonResponse
    {
        $bien = Bien::where('id', $id)
            ->where('agent_id', $request->user()->id)
            ->firstOrFail();

        $request->validate([
            'document'  => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'type'      => 'nullable|string|max:100',
        ]);

        $fichier = $request->file('document');
        $dossier = "biens/{$bien->id}/documents";
        $chemin  = $fichier->store($dossier, 'local');

        $doc = DocumentBien::create([
            'bien_id'      => $bien->id,
            'type'         => $request->input('type', 'autre'),
            'chemin'       => $chemin,
            'nom_original' => $fichier->getClientOriginalName(),
            'taille'       => $fichier->getSize(),
            'mime_type'    => $fichier->getMimeType(),
            'statut'       => 'en_attente',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document ajouté.',
            'data'    => ['id' => $doc->id, 'type' => $doc->type, 'nom' => $doc->nom_original],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE /api/agent/biens/{id}/documents/{docId}
    // Supprimer un document d'un bien
    // ─────────────────────────────────────────────────────────────────────────

    public function deleteDocument(Request $request, string $id, string $docId): JsonResponse
    {
        $bien = Bien::where('id', $id)->where('agent_id', $request->user()->id)->firstOrFail();

        $doc = DocumentBien::where('id', $docId)->where('bien_id', $bien->id)->firstOrFail();

        Storage::disk('local')->delete($doc->chemin);
        $doc->delete();

        return response()->json(['success' => true, 'message' => 'Document supprimé.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/agent/biens/{id}/documents/{docId}/statut
    // Mettre à jour le statut d'un document (conforme / non_conforme)
    // ─────────────────────────────────────────────────────────────────────────

    public function updateDocumentStatut(Request $request, string $id, string $docId): JsonResponse
    {
        $bien = Bien::where('id', $id)->where('agent_id', $request->user()->id)->firstOrFail();

        $request->validate([
            'statut' => 'required|string|max:30',
        ]);

        $doc = DocumentBien::where('id', $docId)->where('bien_id', $bien->id)->firstOrFail();
        
        $doc->update(['statut' => $request->statut]);

        return response()->json([
            'success' => true,
            'message' => 'Statut du document mis à jour.',
            'data'    => $doc->fresh(),
        ]);
    }
}
