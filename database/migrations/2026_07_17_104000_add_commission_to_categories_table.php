<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Pourcentage de commission appliqué au prix propriétaire lors d'une location
            // Ex: 10 = 10% → prix_public = prix + (prix × 10/100)
            $table->decimal('pourcentage_commission', 5, 2)
                  ->default(0)
                  ->after('ordre_affichage')
                  ->comment('Taux de commission ImmoPro appliqué au prix propriétaire (en %)');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('pourcentage_commission');
        });
    }
};
