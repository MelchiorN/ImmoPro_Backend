<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correction du flux de planification des visites client :
 *
 * AVANT (incorrect) : le client proposait des créneaux → l'agent confirmait
 * APRÈS (correct)   : l'agent propose des créneaux → le client choisit l'un d'eux
 *
 * Changements :
 *  - creneaux_client        → creneaux_agent    (l'agent propose)
 *  - creneaux_refuses_le    → supprimé          (plus de "refus", l'agent re-propose)
 *  - refus_agent_note       → supprimé
 *  - statut 'en_attente_agent' → 'en_attente_client' (le client attend pour choisir)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter la colonne creneaux_agent avant de supprimer creneaux_client
        Schema::table('visites', function (Blueprint $table) {
            if (! Schema::hasColumn('visites', 'creneaux_agent')) {
                $table->json('creneaux_agent')
                      ->nullable()
                      ->after('confirme_par_proprio_le')
                      ->comment('Créneaux proposés par l\'agent au client pour planifier la visite');
            }
        });

        // 2. Migrer les données existantes (si besoin)
        if (Schema::hasColumn('visites', 'creneaux_client')) {
            DB::statement('UPDATE visites SET creneaux_agent = creneaux_client WHERE creneaux_client IS NOT NULL');
        }

        // 3. Supprimer les anciennes colonnes
        Schema::table('visites', function (Blueprint $table) {
            $toDrop = [];
            if (Schema::hasColumn('visites', 'creneaux_client'))    $toDrop[] = 'creneaux_client';
            if (Schema::hasColumn('visites', 'creneaux_refuses_le')) $toDrop[] = 'creneaux_refuses_le';
            if (Schema::hasColumn('visites', 'refus_agent_note'))   $toDrop[] = 'refus_agent_note';
            if (! empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });

        // 4. Corriger l'ENUM : en_attente_agent → en_attente_client
        //    Étape a : ajouter les deux valeurs temporairement
        DB::statement("
            ALTER TABLE visites
            MODIFY statut ENUM(
                'proposee',
                'en_attente_agent',
                'en_attente_client',
                'confirmee',
                'annulee',
                'rapport_soumis'
            ) NOT NULL DEFAULT 'proposee'
        ");

        // Étape b : migrer les données
        DB::table('visites')
            ->where('statut', 'en_attente_agent')
            ->update(['statut' => 'en_attente_client']);

        // Étape c : retirer 'en_attente_agent' de l'ENUM
        DB::statement("
            ALTER TABLE visites
            MODIFY statut ENUM(
                'proposee',
                'en_attente_client',
                'confirmee',
                'annulee',
                'rapport_soumis'
            ) NOT NULL DEFAULT 'proposee'
        ");
    }

    public function down(): void
    {
        // Rétablir l'ancien ENUM avec les deux valeurs
        DB::statement("
            ALTER TABLE visites
            MODIFY statut ENUM(
                'proposee',
                'en_attente_agent',
                'en_attente_client',
                'confirmee',
                'annulee',
                'rapport_soumis'
            ) NOT NULL DEFAULT 'proposee'
        ");

        DB::table('visites')
            ->where('statut', 'en_attente_client')
            ->update(['statut' => 'en_attente_agent']);

        DB::statement("
            ALTER TABLE visites
            MODIFY statut ENUM(
                'proposee',
                'en_attente_agent',
                'confirmee',
                'annulee',
                'rapport_soumis'
            ) NOT NULL DEFAULT 'proposee'
        ");

        Schema::table('visites', function (Blueprint $table) {
            if (! Schema::hasColumn('visites', 'creneaux_client')) {
                $table->json('creneaux_client')->nullable()->after('confirme_par_proprio_le');
            }
            if (! Schema::hasColumn('visites', 'creneaux_refuses_le')) {
                $table->timestamp('creneaux_refuses_le')->nullable()->after('creneaux_client');
            }
            if (! Schema::hasColumn('visites', 'refus_agent_note')) {
                $table->text('refus_agent_note')->nullable()->after('creneaux_refuses_le');
            }
        });

        Schema::table('visites', function (Blueprint $table) {
            if (Schema::hasColumn('visites', 'creneaux_agent')) {
                $table->dropColumn('creneaux_agent');
            }
        });
    }
};
