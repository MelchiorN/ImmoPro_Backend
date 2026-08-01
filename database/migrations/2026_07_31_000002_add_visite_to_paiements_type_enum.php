<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter 'visite' à l'ENUM type_paiement de la table paiements
        DB::statement("ALTER TABLE `paiements` MODIFY `type_paiement` ENUM('location','abonnement','frais_etude','visite') NOT NULL");
    }

    public function down(): void
    {
        // Supprimer 'visite' de l'ENUM (attention : échouera si des lignes ont type_paiement='visite')
        DB::statement("ALTER TABLE `paiements` MODIFY `type_paiement` ENUM('location','abonnement','frais_etude') NOT NULL");
    }
};
