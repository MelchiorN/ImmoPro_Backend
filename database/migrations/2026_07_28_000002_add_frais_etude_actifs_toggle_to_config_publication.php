<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * S'assure que frais_etude_actifs existe bien dans config_publication
 * (la colonne a été créée dans 000003, mais sans la logique de toggle admin).
 * Rien à ajouter structurellement — on met juste à jour la valeur par défaut
 * pour que la config initiale soit cohérente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // S'assurer que la ligne config existe avec frais_etude_actifs = false
        DB::table('config_publication')->updateOrInsert(
            ['id' => 1],
            [
                'essais_gratuits_defaut' => DB::table('config_publication')->value('essais_gratuits_defaut') ?? 1,
                'frais_etude_actifs'     => false,
                'updated_at'             => now(),
            ]
        );
    }

    public function down(): void
    {
        // Rien à annuler
    }
};
