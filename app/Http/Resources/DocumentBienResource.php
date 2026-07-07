<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentBienResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'nom_original' => $this->nom_original,
            'statut'       => $this->statut,
            'mime_type'    => $this->mime_type,
            'taille'       => $this->taille,
            // URL privée uniquement si c'est le propriétaire ou admin/agent
            'url'          => $this->when(
                $this->canViewDocument($request),
                fn () => $this->url_privee
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
