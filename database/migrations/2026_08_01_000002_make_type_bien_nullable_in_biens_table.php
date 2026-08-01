<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Rend type_bien nullable pour permettre la création de brouillons
     * sans avoir encore sélectionné le type de bien.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE biens MODIFY COLUMN type_bien VARCHAR(50) NULL DEFAULT NULL");
    }

    public function down(): void
    {
        // Attention : si des lignes ont type_bien = NULL, ce rollback échouera.
        DB::statement("ALTER TABLE biens MODIFY COLUMN type_bien VARCHAR(50) NOT NULL");
    }
};
