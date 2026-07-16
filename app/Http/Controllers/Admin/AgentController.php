<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AgentController extends Controller
{
    /**
     * GET /api/admin/agents
     * Liste paginée avec filtres (search, status)
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', 'agent')
            ->withCount([]) // extensible
            ->latest();

        // Recherche full-text
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name',  'like', "%{$search}%")
                  ->orWhere('email',      'like', "%{$search}%")
                  ->orWhere('telephone',  'like', "%{$search}%");
            });
        }

        // Filtre par statut
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $agents = $query->paginate($request->query('per_page', 15));

        return response()->json($agents);
    }

    /**
     * POST /api/admin/agents
     * Créer un nouvel agent
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'telephone'  => 'required|string|max:20|unique:users,telephone',
            'country'    => 'required|string|max:100',
            'city'       => 'required|string|max:100',
            'password'   => ['required', 'confirmed', Password::min(8)],
        ]);

        $agent = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'telephone'  => $validated['telephone'],
            'country'    => $validated['country'],
            'city'       => $validated['city'],
            'password'   => Hash::make($validated['password']),
            'role'       => 'agent',
            'status'     => 'active',
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($agent)
            ->log("Nouvel agent créé : {$agent->first_name} {$agent->last_name}");

        return response()->json([
            'message' => 'Agent créé avec succès.',
            'agent'   => $agent,
        ], 201);
    }

    /**
     * GET /api/admin/agents/{id}
     * Détail d'un agent
     */
    public function show(string $id): JsonResponse
    {
        $agent = User::where('role', 'agent')->findOrFail($id);

        return response()->json($agent);
    }

    /**
     * PUT /api/admin/agents/{id}
     * Mettre à jour les infos d'un agent
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $agent = User::where('role', 'agent')->findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'email'      => "sometimes|email|unique:users,email,{$agent->id}",
            'telephone'  => "sometimes|string|max:20|unique:users,telephone,{$agent->id}",
            'country'    => 'sometimes|string|max:100',
            'city'       => 'sometimes|string|max:100',
            'password'   => ['sometimes', 'confirmed', Password::min(8)],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $agent->update($validated);

        activity()
            ->causedBy($request->user())
            ->performedOn($agent)
            ->log("Agent mis à jour : {$agent->first_name} {$agent->last_name}");

        return response()->json([
            'message' => 'Agent mis à jour.',
            'agent'   => $agent->fresh(),
        ]);
    }

    /**
     * PATCH /api/admin/agents/{id}/status
     * Changer le statut (active / suspended / blocked)
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $agent = User::where('role', 'agent')->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:active,suspended,blocked',
        ]);

        $agent->update(['status' => $validated['status']]);

        $labels = [
            'active'    => 'activé',
            'suspended' => 'suspendu',
            'blocked'   => 'bloqué',
        ];

        activity()
            ->causedBy($request->user())
            ->performedOn($agent)
            ->withProperties(['status' => $validated['status']])
            ->log("Agent {$labels[$validated['status']]} : {$agent->first_name} {$agent->last_name}");

        return response()->json([
            'message' => "Agent {$labels[$validated['status']]} avec succès.",
            'agent'   => $agent->fresh(),
        ]);
    }

    /**
     * DELETE /api/admin/agents/{id}
     * Supprimer (soft-delete) un agent
     */
    public function destroy(string $id): JsonResponse
    {
        $agent = User::where('role', 'agent')->findOrFail($id);
        $nom   = "{$agent->first_name} {$agent->last_name}";
        $agent->delete();

        activity()
            ->causedBy(request()->user())
            ->withProperties(['agent_id' => $id])
            ->log("Agent supprimé : {$nom}");

        return response()->json([
            'message' => 'Agent supprimé.',
        ]);
    }
}
