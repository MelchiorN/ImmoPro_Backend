<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rend bien_id nullable dans creneaux_visite.
 * L'agent peut créer des créneaux de disponibilité libres, sans bien associé.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Supprimer d'abord la FK (puis l'index composite pourra être supprimé)
        Schema::table('creneaux_visite', function (Blueprint $table) {
            $table->dropForeign(['bien_id']);
        });

        // 2. Supprimer l'index composite bien_id + statut
        Schema::table('creneaux_visite', function (Blueprint $table) {
            $table->dropIndex(['bien_id', 'statut']);
        });

        // 3. Rendre nullable via raw SQL
        DB::statement('ALTER TABLE creneaux_visite MODIFY bien_id CHAR(36) NULL');

        // 4. Recréer la FK nullable + les index
        Schema::table('creneaux_visite', function (Blueprint $table) {
            $table->foreign('bien_id')
                ->references('id')
                ->on('biens')
                ->cascadeOnDelete()
                ->nullOnDelete();

            $table->index(['bien_id', 'statut']);
            $table->index(['agent_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::table('creneaux_visite', function (Blueprint $table) {
            $table->dropForeign(['bien_id']);
            $table->dropIndex(['bien_id', 'statut']);
        });

        // Remettre NOT NULL avec une valeur par défaut pour les lignes existantes
        DB::statement('DELETE FROM creneaux_visite WHERE bien_id IS NULL');
        DB::statement('ALTER TABLE creneaux_visite MODIFY bien_id CHAR(36) NOT NULL');

        Schema::table('creneaux_visite', function (Blueprint $table) {
            $table->foreign('bien_id')->references('id')->on('biens')->cascadeOnDelete();
            $table->index(['bien_id', 'statut']);
        });
    }
};
