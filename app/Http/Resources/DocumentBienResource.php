<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentBienResource extends JsonResource
{
    /** Labels lisibles par type de document. */
    private const TYPE_LABELS = [
        'titre_foncier'  => 'Titre Foncier',
        'piece_identite' => 'Pièce d\'Identité',
        'plan_cadastral' => 'Plan Cadastral',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'label'        => self::TYPE_LABELS[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type)),
            'nom_original' => $this->nom_original,
            'statut'       => $this->statut,
            'mime_type'    => $this->mime_type,
            'taille'       => $this->taille,
            // URL de téléchargement via l'API (sécurisée, authentifiée)
            'url'          => $this->when(
                $this->canViewDocument($request),
                fn () => url("/api/agent/documents/{$this->id}")
            ),
        ];
    }

    private function canViewDocument(Request $request): bool
    {
        $user = $request->user();
        if (! $user) return false;

        return $user->id === $this->bien->user_id
            || in_array($user->role, ['admin', 'agent']);
    }
}

