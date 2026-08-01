<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visite extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'bien_id',
        'agent_id',
        'client_id',
        'date_visite',
        'notes',
        'statut',
        'rapport',
        'visite_effectuee',
        'est_payee',
        'type_visite',
        'duree_minutes',
        'proprio_note',
        'confirme_par_proprio_le',
        'rappels_envoyes',
        // Planification agent → client
        'creneaux_agent',         // créneaux proposés par l'agent au client
        'nb_indisponibilites',    // nombre de fois que le client a signalé son indisponibilité
        'note_indisponibilite',   // dernière note du client pour l'indisponibilité
    ];

    protected function casts(): array
    {
        return [
            'date_visite'             => 'datetime',
            'visite_effectuee'        => 'boolean',
            'est_payee'               => 'boolean',
            'confirme_par_proprio_le' => 'datetime',
            'rappels_envoyes'         => 'array',
            'creneaux_agent'          => 'array',
            'nb_indisponibilites'     => 'integer',
        ];
    }

    // Statuts possibles
    public const STATUT_PROPOSEE           = 'proposee';          // visite payée, agent doit proposer des créneaux
    public const STATUT_EN_ATTENTE_CLIENT  = 'en_attente_client'; // agent a proposé, client doit choisir
    public const STATUT_INDISPONIBLE       = 'indisponible';      // client indisponible, agent doit re-proposer
    public const STATUT_CONFIRMEE          = 'confirmee';          // client a choisi un créneau
    public const STATUT_ANNULEE            = 'annulee';
    public const STATUT_RAPPORT_SOUMIS     = 'rapport_soumis';

    // Alias rétrocompatible
    public const STATUT_EN_ATTENTE_AGENT   = 'en_attente_client';

    public const TYPE_VERIFICATION = 'verification';
    public const TYPE_CLIENT       = 'client';

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
