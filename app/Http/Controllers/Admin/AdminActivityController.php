<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AdminActivityController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/activities
    // Journal global de toutes les activités de la plateforme
    // Filtres : causer_id, subject_type, log_name, search, per_page
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Activity::with(['causer', 'subject'])
            ->latest();

        // Filtrer par auteur de l'action
        if ($causerId = $request->query('causer_id')) {
            $query->where('causer_id', $causerId);
        }

        // Filtrer par type de sujet (ex: App\Models\Bien, App\Models\User)
        if ($subjectType = $request->query('subject_type')) {
            $query->where('subject_type', 'like', "%{$subjectType}%");
        }

        // Filtrer par nom de log
        if ($logName = $request->query('log_name')) {
            $query->where('log_name', $logName);
        }

        // Recherche dans la description
        if ($search = $request->query('search')) {
            $query->where('description', 'like', "%{$search}%");
        }

        $activities = $query->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $activities->getCollection()->map(fn ($a) => $this->format($a))->values(),
            'meta'    => [
                'total'        => $activities->total(),
                'per_page'     => $activities->perPage(),
                'current_page' => $activities->currentPage(),
                'last_page'    => $activities->lastPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/activities/user/{id}
    // Toutes les actions effectuées PAR un utilisateur précis
    // ─────────────────────────────────────────────────────────────────────────
    public function byUser(string $id): JsonResponse
    {
        $activities = Activity::with(['subject'])
            ->where('causer_type', 'App\\Models\\User')
            ->where('causer_id', $id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($a) => $this->format($a));

        return response()->json([
            'success' => true,
            'data'    => $activities,
        ]);
    }

    // ─── Helper format ────────────────────────────────────────────────────────
    private function format(Activity $a): array
    {
        // Nom du sujet (ex: titre d'un bien, email d'un user)
        $subjectLabel = null;
        if ($a->subject) {
            $subjectLabel = $a->subject->titre
                ?? $a->subject->email
                ?? $a->subject->first_name . ' ' . ($a->subject->last_name ?? '');
        }

        // Nom court du type de sujet (ex: "Bien", "User")
        $subjectType = $a->subject_type
            ? class_basename($a->subject_type)
            : null;

        return [
            'id'            => $a->id,
            'description'   => $a->description,
            'log_name'      => $a->log_name,
            'subject_type'  => $subjectType,
            'subject_id'    => $a->subject_id,
            'subject_label' => $subjectLabel,
            'properties'    => $a->properties,
            'causer'        => $a->causer ? [
                'id'         => $a->causer->id,
                'first_name' => $a->causer->first_name,
                'last_name'  => $a->causer->last_name,
                'role'       => $a->causer->role,
            ] : null,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }
}
