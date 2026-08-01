<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('config_publication', function (Blueprint $table) {
            // SLA 1 — délai max avant alerte : dossier sans agent
            $table->unsignedSmallInteger('sla1_valeur')->default(2)->after('frais_etude_actifs');
            $table->enum('sla1_unite', ['minutes', 'heures', 'jours', 'semaines', 'mois'])->default('heures')->after('sla1_valeur');

            // SLA 2 — délai max avant alerte : dossier en cours sans clôture
            $table->unsignedSmallInteger('sla2_valeur')->default(7)->after('sla1_unite');
            $table->enum('sla2_unite', ['minutes', 'heures', 'jours', 'semaines', 'mois'])->default('jours')->after('sla2_valeur');

            // Durée par défaut d'une visite
            $table->unsignedSmallInteger('visite_duree_valeur')->default(45)->after('sla2_unite');
            $table->enum('visite_duree_unite', ['minutes', 'heures', 'jours', 'semaines', 'mois'])->default('minutes')->after('visite_duree_valeur');

            // Délai minimum entre deux visites (anti-collision)
            $table->unsignedSmallInteger('visite_delai_min_valeur')->default(12)->after('visite_duree_unite');
            $table->enum('visite_delai_min_unite', ['minutes', 'heures', 'jours', 'semaines', 'mois'])->default('heures')->after('visite_delai_min_valeur');
        });
    }

    public function down(): void
    {
        Schema::table('config_publication', function (Blueprint $table) {
            $table->dropColumn([
                'sla1_valeur', 'sla1_unite',
                'sla2_valeur', 'sla2_unite',
                'visite_duree_valeur', 'visite_duree_unite',
                'visite_delai_min_valeur', 'visite_delai_min_unite',
            ]);
        });
    }
};
