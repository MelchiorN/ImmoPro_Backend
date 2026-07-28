<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('frais_etude_pourcentage', 5, 2)
                  ->default(0)
                  ->after('pourcentage_commission')
                  ->comment('Pourcentage appliqué sur le prix du bien pour calculer les frais d\'étude de dossier. Ex: 5 = 5% du prix.');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('frais_etude_pourcentage');
        });
    }
};
