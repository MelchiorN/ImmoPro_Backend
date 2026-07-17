<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ajoute le statut 'valide' à l'enum de la colonne statut des biens.
     * Ce statut intermédiaire signifie : approuvé par l'admin, en attente
     * de publication par le propriétaire lui-même.
     */
    public function up(): void
    {
        // SQLite ne supporte pas ALTER COLUMN sur enum → on change le type en string
        // Pour MySQL, on modifie l'enum directement
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE biens MODIFY COLUMN statut ENUM(
                'brouillon',
                'en_attente',
                'en_cours',
                'valide',
                'publie',
                'rejete',
                'archive'
            ) NOT NULL DEFAULT 'en_attente'");
        }
        // SQLite : la colonne est déjà gérée comme string, aucune modification nécessaire
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // Remettre les biens 'valide' en 'en_cours' avant de supprimer le statut
            DB::statement("UPDATE biens SET statut = 'en_cours' WHERE statut = 'valide'");

            DB::statement("ALTER TABLE biens MODIFY COLUMN statut ENUM(
                'brouillon',
                'en_attente',
                'en_cours',
                'publie',
                'rejete',
                'archive'
            ) NOT NULL DEFAULT 'en_attente'");
        }
    }
};
