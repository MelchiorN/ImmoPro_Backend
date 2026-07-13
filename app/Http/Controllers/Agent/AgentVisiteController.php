<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Mail\VisitePlanifieeNotification;
use App\Models\Bien;
use App\Models\Visite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AgentVisiteController extends Controller
{
    // ── POST /api/agent/biens/{id}/visites — Planifier une visite ────────────
    public function store(Request $request, string $bienId): JsonResponse
    {
        $agentId = $request->user()->id;

        $request->validate([
            // Tolérance de 5 min pour les décalages horloge mobile/serveur
            'date_visite' => 'required|date|after_or_equal:' . now()->subMinutes(5)->toDateTimeString(),
            'notes'       => 'nullable|string|max:500',
        ], [
            'date_visite.required'        => 'La date et l\'heure de visite sont obligatoires.',
            'date_visite.date'            => 'Le format de la date est invalide.',
            'date_visite.after_or_equal'  => 'La date de visite doit être dans le futur.',
            'notes.max'                   => 'Les notes ne doivent pas dépasser 500 caractères.',
        ]);

        // Vérifier que l'agent a ce bien en charge
        // Le bien doit être en statut 'en_cours' (assigné à l'agent) — pas 'en_attente'
        $bien = Bien::where('id', $bienId)
                    ->where('agent_id', $agentId)
                    ->whereIn('statut', ['en_cours', 'en_attente'])
                    ->with(['proprietaire'])
                    ->firstOrFail();

        // Pas de doublon de visite planifiée
        $existing = Visite::where('bien_id', $bienId)
                          ->whereIn('statut', ['planifiee', 'confirmee'])
                          ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Une visite est déjà planifiée pour ce bien.',
                'visite'  => $this->formatVisite($existing),
            ], 409);
        }

        $visite = Visite::create([
            'bien_id'     => $bienId,
            'agent_id'    => $agentId,
            'date_visite' => $request->input('date_visite'),
            'notes'       => $request->input('notes'),
            'statut'      => 'planifiee',
        ]);

        // ── Envoyer un email au client propriétaire ────────────────────────────
        if ($bien->proprietaire && $bien->proprietaire->email) {
            try {
                $visite->load(['bien', 'agent']);
                Mail::to($bien->proprietaire->email)
                    ->send(new VisitePlanifieeNotification($visite));
            } catch (\Throwable $e) {
                // L'email ne doit pas bloquer la réponse
                Log::warning('Email visite non envoyé : ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Visite planifiée avec succès. Le propriétaire a été notifié par email.',
            'visite'  => $this->formatVisite($visite),
        ], 201);
    }

    // ── GET /api/agent/biens/{id}/visites — Lister les visites d'un bien ────
    public function index(Request $request, string $bienId): JsonResponse
    {
        $agentId = $request->user()->id;

        $visites = Visite::where('bien_id', $bienId)
                         ->where('agent_id', $agentId)
                         ->orderBy('date_visite')
                         ->get();

        return response()->json([
            'success' => true,
            'data'    => $visites->map(fn ($v) => $this->formatVisite($v)),
        ]);
    }

    // ── GET /api/agent/visites — Toutes les visites de l'agent (calendrier) ──
    public function allVisites(Request $request): JsonResponse
    {
        $agentId = $request->user()->id;

        $visites = Visite::with(['bien'])
            ->where('agent_id', $agentId)
            ->orderBy('date_visite')
            ->get()
            ->map(function (Visite $v) {
                return array_merge($this->formatVisite($v), [
                    'bien_titre'   => $v->bien?->titre,
                    'bien_adresse' => $v->bien?->adresse,
                    'bien_id'      => $v->bien_id,
                ]);
            });

        return response()->json([
            'success' => true,
            'data'    => $visites,
        ]);
    }

    // ── PATCH /api/agent/visites/{id} — Confirmer / Annuler / Soumettre rapport
    public function update(Request $request, string $visiteId): JsonResponse
    {
        $agentId = $request->user()->id;

        $visite = Visite::where('id', $visiteId)
                        ->where('agent_id', $agentId)
                        ->firstOrFail();

        $request->validate([
            'statut'           => 'required|in:confirmee,annulee,rapport_soumis',
            'rapport'          => 'required_if:statut,rapport_soumis|nullable|string',
            'visite_effectuee' => 'required_if:statut,rapport_soumis|nullable|boolean',
        ]);

        $visite->update($request->only('statut', 'rapport', 'visite_effectuee'));

        return response()->json([
            'success' => true,
            'message' => 'Visite mise à jour.',
            'visite'  => $this->formatVisite($visite->fresh()),
        ]);
    }

    private function formatVisite(Visite $v): array
    {
        return [
            'id'               => $v->id,
            'date_visite'      => $v->date_visite?->toIso8601String(),
            'notes'            => $v->notes,
            'statut'           => $v->statut,
            'rapport'          => $v->rapport,
            'visite_effectuee' => $v->visite_effectuee,
            'created_at'       => $v->created_at->toIso8601String(),
        ];
    }
}
