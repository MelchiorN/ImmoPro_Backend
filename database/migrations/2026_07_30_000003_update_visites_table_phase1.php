<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Étape 1 : étendre l'ENUM pour inclure 'proposee' EN PLUS de 'planifiee'
        // (MySQL refuse d'écrire une valeur absente de l'ENUM)
        DB::statement("ALTER TABLE visites MODIFY statut ENUM('planifiee','proposee','confirmee','annulee','rapport_soumis') NOT NULL DEFAULT 'planifiee'");

        // Étape 2 : ajouter les nouvelles colonnes (si pas déjà là — idempotent)
        Schema::table('visites', function (Blueprint $table) {
            if (! Schema::hasColumn('visites', 'type_visite')) {
                $table->enum('type_visite', ['verification', 'client'])->default('verification')->after('agent_id');
            }
            if (! Schema::hasColumn('visites', 'duree_minutes')) {
                $table->unsignedSmallInteger('duree_minutes')->default(45)->after('type_visite');
            }
            if (! Schema::hasColumn('visites', 'proprio_note')) {
                $table->text('proprio_note')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('visites', 'confirme_par_proprio_le')) {
                $table->timestamp('confirme_par_proprio_le')->nullable()->after('proprio_note');
            }
            if (! Schema::hasColumn('visites', 'rappels_envoyes')) {
                $table->json('rappels_envoyes')->nullable()->after('visite_effectuee');
            }
        });

        // Étape 3 : migrer 'planifiee' → 'proposee' sur les données existantes
        DB::table('visites')->where('statut', 'planifiee')->update(['statut' => 'proposee']);

        // Étape 4 : retirer 'planifiee' de l'ENUM et changer le default
        DB::statement("ALTER TABLE visites MODIFY statut ENUM('proposee','confirmee','annulee','rapport_soumis') NOT NULL DEFAULT 'proposee'");
    }

    public function down(): void
    {
        // Remettre 'proposee' en 'planifiee' avant de restaurer l'ENUM
        DB::table('visites')->where('statut', 'proposee')->update(['statut' => 'planifiee']);

        DB::statement("ALTER TABLE visites MODIFY statut ENUM('planifiee','confirmee','annulee','rapport_soumis') NOT NULL DEFAULT 'planifiee'");

        Schema::table('visites', function (Blueprint $table) {
            $table->dropColumn(['type_visite', 'duree_minutes', 'proprio_note', 'confirme_par_proprio_le', 'rappels_envoyes']);
        });
    }
};
