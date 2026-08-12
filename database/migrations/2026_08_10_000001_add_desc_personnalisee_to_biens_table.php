<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            // Description enrichie générée par Gemini lors de la validation/approbation.
            // Stockée une seule fois pour éviter d'épuiser les quotas API à chaque consultation.
            $table->longText('desc_personnalisee')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            $table->dropColumn('desc_personnalisee');
        });
    }
};
