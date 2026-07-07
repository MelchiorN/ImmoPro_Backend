<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DocumentBien extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'bien_id',
        'type',
        'chemin',
        'nom_original',
        'taille',
        'mime_type',
        'statut',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'taille' => 'integer',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** URL de téléchargement privée (uniquement pour propriétaire/admin). */
    public function getUrlPriveeAttribute(): string
    {
        return Storage::disk('local')->url($this->chemin);
    }
}
