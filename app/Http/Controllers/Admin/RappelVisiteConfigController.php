<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RappelVisiteConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RappelVisiteConfigController extends Controller
{
    // GET /api/admin/rappels-visite
    public function index(): JsonResponse
    {
        $rappels = RappelVisiteConfig::orderBy('ordre')->get()
            ->map(fn ($r) => $this->format($r));

        return response()->json(['success' => true, 'data' => $rappels]);
    }

    // POST /api/admin/rappels-visite
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'valeur'       => 'required|integer|min:0',
            'unite'        => 'required|in:minutes,heures,jours,semaines',
            'est_jour_j'   => 'boolean',
            'heure_jour_j' => 'required_if:est_jour_j,true|nullable|date_format:H:i',
            'actif'        => 'boolean',
            'ordre'        => 'nullable|integer',
        ]);

        $rappel = RappelVisiteConfig::create($data);

        return response()->json(['success' => true, 'data' => $this->format($rappel)], 201);
    }

    // PATCH /api/admin/rappels-visite/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $rappel = RappelVisiteConfig::findOrFail($id);

        $data = $request->validate([
            'valeur'       => 'sometimes|integer|min:0',
            'unite'        => 'sometimes|in:minutes,heures,jours,semaines',
            'est_jour_j'   => 'sometimes|boolean',
            'heure_jour_j' => 'nullable|date_format:H:i',
            'actif'        => 'sometimes|boolean',
            'ordre'        => 'sometimes|integer',
        ]);

        $rappel->update($data);

        return response()->json(['success' => true, 'data' => $this->format($rappel->fresh())]);
    }

    // DELETE /api/admin/rappels-visite/{id}
    public function destroy(int $id): JsonResponse
    {
        RappelVisiteConfig::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Rappel supprimé.']);
    }

    private function format(RappelVisiteConfig $r): array
    {
        return [
            'id'           => $r->id,
            'valeur'       => $r->valeur,
            'unite'        => $r->unite,
            'est_jour_j'   => $r->est_jour_j,
            'heure_jour_j' => $r->heure_jour_j,
            'actif'        => $r->actif,
            'ordre'        => $r->ordre,
            'label'        => $r->label(),
        ];
    }
}
