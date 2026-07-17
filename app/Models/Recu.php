<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recu extends Model
{
    use HasUuids;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'paiement_id',
        'numero_recu',
        'fichier_pdf',
        'date_emission',
    ];

    protected function casts(): array
    {
        return [
            'date_emission' => 'datetime',
        ];
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    /**
     * Génère un numéro de reçu unique : REC-2026-XXXX
     */
    public static function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = static::where('numero_recu', 'like', "REC-{$annee}-%")
            ->orderByDesc('numero_recu')
            ->value('numero_recu');

        $sequence = 1;
        if ($dernier) {
            $parts = explode('-', $dernier);
            $sequence = (int) end($parts) + 1;
        }

        return sprintf('REC-%d-%04d', $annee, $sequence);
    }
}
