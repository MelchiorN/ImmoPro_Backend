<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les frais d'étude ne sont plus un montant fixe global.
 * Ils sont désormais un pourcentage défini par catégorie de bien
 * (colonne frais_etude_pourcentage sur la table categories).
 *
 * On retire donc frais_etude_dossier de config_publication.
 * On conserve frais_etude_actifs comme interrupteur global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('config_publication', function (Blueprint $table) {
            $table->dropColumn('frais_etude_dossier');
        });
    }

    public function down(): void
    {
        Schema::table('config_publication', function (Blueprint $table) {
            $table->decimal('frais_etude_dossier', 15, 2)
                  ->default(0)
                  ->after('essais_gratuits_defaut')
                  ->comment('Montant fixe des frais d\'étude (remplacé par un pourcentage par catégorie)');
        });
    }
};
