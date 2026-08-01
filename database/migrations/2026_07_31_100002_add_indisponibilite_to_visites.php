<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute le statut 'indisponible' à l'ENUM des visites et la colonne
 * pour compter le nombre de fois que le client a signalé son indisponibilité.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Étendre l'ENUM avec 'indisponible'
        DB::statement("
            ALTER TABLE visites
            MODIFY statut ENUM(
                'proposee',
                'en_attente_client',
                'indisponible',
                'confirmee',
                'annulee',
                'rapport_soumis'
            ) NOT NULL DEFAULT 'proposee'
        ");

        Schema::table('visites', function (Blueprint $table) {
            if (! Schema::hasColumn('visites', 'nb_indisponibilites')) {
                $table->unsignedSmallInteger('nb_indisponibilites')
                    ->default(0)
                    ->after('creneaux_agent')
                    ->comment('Nombre de fois que le client a signalé son indisponibilité');
            }
            if (! Schema::hasColumn('visites', 'note_indisponibilite')) {
                $table->text('note_indisponibilite')
                    ->nullable()
                    ->after('nb_indisponibilites')
                    ->comment('Dernière note du client pour justifier l\'indisponibilité');
            }
        });
    }

    public function down(): void
    {
        Schema::table('visites', function (Blueprint $table) {
            $toDrop = [];
            if (Schema::hasColumn('visites', 'nb_indisponibilites'))   $toDrop[] = 'nb_indisponibilites';
            if (Schema::hasColumn('visites', 'note_indisponibilite'))  $toDrop[] = 'note_indisponibilite';
            if (! empty($toDrop)) $table->dropColumn($toDrop);
        });

        DB::table('visites')->where('statut', 'indisponible')->update(['statut' => 'proposee']);

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
};
