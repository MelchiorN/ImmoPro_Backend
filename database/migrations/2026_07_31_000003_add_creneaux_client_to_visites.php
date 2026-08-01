<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Étendre l'ENUM statut avec les nouveaux états du flux client
        //    - 'proposee'           : visite payée, planification à définir
        //    - 'en_attente_agent'   : le client a proposé des créneaux, l'agent n'a pas encore répondu
        //    - 'confirmee'          : l'agent a confirmé un créneau (date_visite fixée)
        //    - 'annulee'            : annulée (client ou agent)
        //    - 'rapport_soumis'     : rapport soumis par l'agent après la visite
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

        // 2. Ajouter la colonne creneaux_client : tableau JSON de créneaux proposés par le client
        //    Structure attendue de chaque item :
        //    { "date_debut": "2026-08-05T10:00:00", "duree_minutes": 60, "note": "..." }
        Schema::table('visites', function (Blueprint $table) {
            if (! Schema::hasColumn('visites', 'creneaux_client')) {
                $table->json('creneaux_client')
                      ->nullable()
                      ->after('confirme_par_proprio_le')
                      ->comment('Créneaux proposés par le client pour planifier la visite');
            }
            if (! Schema::hasColumn('visites', 'creneaux_refuses_le')) {
                $table->timestamp('creneaux_refuses_le')
                      ->nullable()
                      ->after('creneaux_client')
                      ->comment('Date à laquelle l\'agent a refusé les créneaux proposés par le client');
            }
            if (! Schema::hasColumn('visites', 'refus_agent_note')) {
                $table->text('refus_agent_note')
                      ->nullable()
                      ->after('creneaux_refuses_le')
                      ->comment('Note de l\'agent expliquant le refus des créneaux');
            }
        });
    }

    public function down(): void
    {
        Schema::table('visites', function (Blueprint $table) {
            $table->dropColumn(['creneaux_client', 'creneaux_refuses_le', 'refus_agent_note']);
        });

        // Remettre l'ENUM sans les nouveaux états
        DB::statement("
            ALTER TABLE visites
            MODIFY statut ENUM(
                'proposee',
                'confirmee',
                'annulee',
                'rapport_soumis'
            ) NOT NULL DEFAULT 'proposee'
        ");
    }
};
