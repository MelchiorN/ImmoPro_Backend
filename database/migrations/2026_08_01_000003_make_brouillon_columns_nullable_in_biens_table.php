<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Rend nullable toutes les colonnes de la table `biens` qui peuvent être
 * absentes lors de la création d'un brouillon (données partielles).
 *
 * Colonnes concernées :
 *  - titre        : pas encore saisi en step 1
 *  - prix         : pas encore saisi en step 1
 *  - adresse      : pas encore saisie en step 1
 *  - latitude     : géolocalisation optionnelle
 *  - longitude    : géolocalisation optionnelle
 *  - type_transaction : déjà corrigé via step 1 defaults mais peut manquer
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE biens MODIFY COLUMN titre VARCHAR(255) NULL DEFAULT NULL");
        DB::statement("ALTER TABLE biens MODIFY COLUMN prix DECIMAL(15,2) NULL DEFAULT NULL");
        DB::statement("ALTER TABLE biens MODIFY COLUMN adresse VARCHAR(500) NULL DEFAULT NULL");
        DB::statement("ALTER TABLE biens MODIFY COLUMN latitude DECIMAL(10,7) NULL DEFAULT NULL");
        DB::statement("ALTER TABLE biens MODIFY COLUMN longitude DECIMAL(10,7) NULL DEFAULT NULL");
        DB::statement("ALTER TABLE biens MODIFY COLUMN type_transaction ENUM('vente','location','colocation') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        // Attention : rollback possible uniquement si aucune ligne n'a ces champs à NULL
        DB::statement("ALTER TABLE biens MODIFY COLUMN titre VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE biens MODIFY COLUMN prix DECIMAL(15,2) NOT NULL");
        DB::statement("ALTER TABLE biens MODIFY COLUMN adresse VARCHAR(500) NOT NULL");
        DB::statement("ALTER TABLE biens MODIFY COLUMN latitude DECIMAL(10,7) NOT NULL");
        DB::statement("ALTER TABLE biens MODIFY COLUMN longitude DECIMAL(10,7) NOT NULL");
        DB::statement("ALTER TABLE biens MODIFY COLUMN type_transaction ENUM('vente','location','colocation') NOT NULL");
    }
};
