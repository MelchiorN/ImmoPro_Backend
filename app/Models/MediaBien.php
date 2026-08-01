<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaBien extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'bien_id',
        'type',
        'chemin',
        'url',
        'est_principale',
        'ordre',
        'taille',
        'mime_type',
    ];

    protected function casts(): array
    {
        return [
            'est_principale' => 'boolean',
            'ordre'          => 'integer',
            'taille'         => 'integer',
        ];
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * Recalcule l'URL publique dynamiquement depuis APP_URL.
     * Ignore la valeur stockée en base — toujours cohérent avec l'environnement courant.
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->chemin
                ? Storage::disk('public')->url($this->chemin)
                : null,
        );
    }

    /**
     * Alias rétrocompatible — même résultat que ->url.
     */
    public function getUrlPubliqueAttribute(): string
    {
        return Storage::disk('public')->url($this->chemin);
    }
}
