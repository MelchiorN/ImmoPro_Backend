<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table de configuration des types de documents justificatifs.
 * Remplace l'ENUM hardcodé dans document_biens.type
 * et les listes fixes dans BienController, StoreBienRequest, Step3Flutter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_types_document', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 100)->unique();
            $table->string('nom', 150);
            $table->string('description', 500)->nullable();

            // Formats de fichier acceptés (JSON : ["pdf","jpg","png"])
            // null = tous les formats définis par FileLimits
            $table->json('formats_acceptes')->nullable();

            // Taille max en octets (null = limite globale)
            $table->unsignedInteger('taille_max_octets')->nullable();

            // Document commun à tous les rôles déposant ?
            $table->boolean('commun_tous_roles')->default(false);

            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['actif', 'ordre']);
        });

        // Convertir document_biens.type de ENUM → VARCHAR(100)
        // et document_biens.statut de ENUM → VARCHAR(30)
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE document_biens MODIFY COLUMN type VARCHAR(100) NOT NULL"
        );
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE document_biens MODIFY COLUMN statut VARCHAR(30) NOT NULL DEFAULT 'en_attente'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('config_types_document');
    }
};
