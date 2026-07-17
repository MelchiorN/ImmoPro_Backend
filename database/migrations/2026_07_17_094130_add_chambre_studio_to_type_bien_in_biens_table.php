<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // On modifie l'enum pour ajouter 'chambre_studio'
        DB::statement("ALTER TABLE biens MODIFY COLUMN type_bien ENUM('appartement','maison','villa','terrain','bureau_commerce','chambre_studio') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // En cas de rollback, si 'chambre_studio' a été utilisé, on ne peut pas vraiment le retirer sans perte de données,
        // mais on redéfinit l'enum d'origine. (Attention aux données existantes).
        DB::statement("ALTER TABLE biens MODIFY COLUMN type_bien ENUM('appartement','maison','villa','terrain','bureau_commerce') NOT NULL");
    }
};
