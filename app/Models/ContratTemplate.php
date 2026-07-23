<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContratTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'contrats_templates';

    protected $fillable = [
        'titre',
        'description',
        'type',
        'contenu_html',
        'est_actif',
        'est_defaut',
    ];

    protected $casts = [
        'est_actif'  => 'boolean',
        'est_defaut' => 'boolean',
    ];
}
