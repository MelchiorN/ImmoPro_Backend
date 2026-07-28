<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            $table->string('statut', 50)->default('en_attente_paiement')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            $table->enum('statut', [
                'brouillon',
                'en_attente',
                'en_cours',
                'valide',
                'publie',
                'rejete',
                'archive',
            ])->default('en_attente')->change();
        });
    }
};
