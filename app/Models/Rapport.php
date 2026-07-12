<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rapport extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'bien_id',
        'agent_id',
        'titre',
        'contenu',
        'statut',
        'checklist',
        'note_finale',
        'note_rejet',
        'soumis_le',
    ];

    public function getNoteRejetAttribute($value)
    {
        return $value ?? $this->note_finale;
    }

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'soumis_le' => 'datetime',
        ];
    }

    // ─── Statuts possibles ────────────────────────────────────────────────────
    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_SOUMIS    = 'soumis';
    public const STATUT_VALIDE    = 'valide';
    public const STATUT_REJETE    = 'rejete';

    // ─── Relations ────────────────────────────────────────────────────────────

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
