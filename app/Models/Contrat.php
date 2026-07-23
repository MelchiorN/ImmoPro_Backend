<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contrat extends Model
{
    use HasUuids;

    protected $table = 'contrats';

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'location_id',
        'contenu_html',
        'fichier_pdf',
        'url_pdf',
        'date_generation',
        'date_creation',
        'date_acceptation',
        'statut_signature',
    ];

    protected $appends = [
        'idContrat',
        'urlPdf',
        'dateCreation',
        'statutSignature',
    ];

    protected function casts(): array
    {
        return [
            'date_generation'  => 'datetime',
            'date_creation'    => 'datetime',
            'date_acceptation' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    // ── Accessors (compatibilité camelCase) ──────────────────────────────────

    public function getIdContratAttribute()
    {
        return $this->id;
    }

    public function getUrlPdfAttribute(): ?string
    {
        return $this->url_pdf ?? $this->fichier_pdf;
    }

    public function getDateCreationAttribute()
    {
        return $this->date_creation ?? $this->date_generation ?? $this->created_at;
    }

    public function getStatutSignatureAttribute(): string
    {
        return $this->attributes['statut_signature'] ?? 'en_attente';
    }
}
