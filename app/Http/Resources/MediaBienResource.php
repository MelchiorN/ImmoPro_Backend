<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaBienResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'url'            => $this->url_publique,
            'est_principale' => $this->est_principale,
            'ordre'          => $this->ordre,
            'mime_type'      => $this->mime_type,
            'taille'         => $this->taille,
        ];
    }
}
