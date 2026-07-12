<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite ne supporte pas ALTER COLUMN ENUM — on modifie directement la contrainte.
        // Pour MySQL/MariaDB : modifier l'enum ; pour SQLite : recréer la colonne n'est pas
        // nécessaire car SQLite ignore les contraintes ENUM à l'exécution.
        // On utilise une approche compatible avec les deux.

        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE biens MODIFY COLUMN statut ENUM(
                'brouillon',
                'en_attente',
                'en_cours',
                'publie',
                'rejete',
                'archive'
            ) NOT NULL DEFAULT 'en_attente'");
        }
        // SQLite : pas de modification nécessaire, les valeurs ENUM ne sont pas enforced.

        // Migrer les données existantes :
        // Les biens en_attente avec agent_id passent à en_cours
        DB::table('biens')
            ->where('statut', 'en_attente')
            ->whereNotNull('agent_id')
            ->update(['statut' => 'en_cours']);
    }

    public function down(): void
    {
        // Remettre en_cours → en_attente
        DB::table('biens')
            ->where('statut', 'en_cours')
            ->update(['statut' => 'en_attente']);

        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE biens MODIFY COLUMN statut ENUM(
                'brouillon',
                'en_attente',
                'publie',
                'rejete',
                'archive'
            ) NOT NULL DEFAULT 'en_attente'");
        }
    }
};
