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
        DB::statement("ALTER TABLE biens MODIFY COLUMN type_bien VARCHAR(50) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE biens MODIFY COLUMN type_bien ENUM('appartement','maison','villa','terrain','bureau_commerce','chambre_studio') NOT NULL");
    }
};
