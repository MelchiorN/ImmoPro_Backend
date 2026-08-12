<?php

namespace App\Http\Controllers\Admin;

use App\Events\BienStatutChanged;
use App\Events\DossierAssigneEvent;
use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\User;
use App\Notifications\DossierAssigneAdminNotification;
use App\Notifications\DossierPrisEnChargeNotification;
use App\Services\DureeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDossierController extends Controller
{
    /**
     * Retourne la liste des dossiers pour le dashboard admin.
     */
    public function index(Request $request): JsonResponse
    {
        $biens = Bien::with(['proprietaire', 'agent'])
            ->whereIn('statut', ['en_attente', 'en_cours', 'publie', 'rejete'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($bien) {
                return [
                    'id' => $bien->id,
                    'titre' => $bien->titre,
                    'statut' => $bien->statut,
                    'proprietaire' => $bien->proprietaire ? $bien->proprietaire->name : 'N/A',
                    'agent' => $bien->agent ? $bien->agent->name : null,
                    'submitted_at' => $bien->submitted_at,
                    'claimed_at' => $bien->claimed_at,
                    'last_activity_at' => $bien->last_activity_at,
                    'sla1_alerted_at' => $bien->sla1_alerted_at,
                    'sla2_alerted_at' => $bien->sla2_alerted_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $biens
        ]);
    }

    /**
     * Affectation forcée par l'admin d'un agent à un dossier.
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'agent_id' => 'required|exists:users,id'
        ]);

        $agent = User::where('id', $request->agent_id)->where('role', 'agent')->firstOrFail();
        
        $bien = Bien::findOrFail($id);

        if ($bien->statut !== 'en_attente' && $bien->statut !== 'en_cours') {
            return response()->json(['success' => false, 'message' => 'Le bien ne peut pas être assigné dans ce statut.'], 400);
        }

        $bien->agent_id = $agent->id;
        $bien->statut = 'en_cours';
        $bien->claimed_at = now();
        $bien->last_activity_at = now();
        $bien->save();

        // Notifier déposant et autres admins
        if ($bien->proprietaire) {
            $bien->proprietaire->notify(new DossierPrisEnChargeNotification($bien, $agent));
        }

        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $adm) {
            $adm->notify(new DossierAssigneAdminNotification($bien, $agent));
        }

        // ── Broadcast temps réel ──────────────────────────────────────────────
        broadcast(new DossierAssigneEvent($bien->fresh(), $agent))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'L\'agent a été assigné avec succès.',
            'data' => $bien
        ]);
    }

    /**
     * Détail d'un dossier pour l'admin.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $bien = Bien::with(['proprietaire', 'agent'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $bien->id,
                'titre' => $bien->titre,
                'statut' => $bien->statut,
                'proprietaire' => $bien->proprietaire ? $bien->proprietaire->name : 'N/A',
                'agent' => $bien->agent ? $bien->agent->name : null,
                'submitted_at' => $bien->submitted_at,
                'claimed_at' => $bien->claimed_at,
                'last_activity_at' => $bien->last_activity_at,
                'sla1_alerted_at' => $bien->sla1_alerted_at,
                'sla2_alerted_at' => $bien->sla2_alerted_at,
            ]
        ]);
    }

    /**
     * Retirer un bien publié (unpublish).
     */
    public function withdraw(Request $request, string $id): JsonResponse
    {
        $bien = Bien::findOrFail($id);

        if ($bien->statut !== 'publie') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un bien publié peut être retiré.'
            ], 400);
        }

        $bien->statut = 'rejete';
        $bien->note_admin = $request->input('motif', 'Retiré par l\'administrateur.');
        $bien->save();

        // ── Broadcast temps réel — notifier l'agent et le propriétaire ────────
        try {
            broadcast(new BienStatutChanged($bien->fresh()));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[AdminDossier] Broadcast withdraw échoué : ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'L\'annonce a été retirée avec succès.',
            'data' => $bien
        ]);
    }
}

