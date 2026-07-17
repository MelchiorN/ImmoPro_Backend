<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            // Colonne JSON pour les champs dynamiques par catégorie.
            // Ajoutée après nb_salles_bain, nullable pour ne pas casser les biens existants.
            $table->json('caracteristiques')->nullable()->after('nb_salles_bain');
        });
    }

    public function down(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            $table->dropColumn('caracteristiques');
        });
    }
};
