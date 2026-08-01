<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table de configuration des types de transaction (vente, location, colocation…).
 * Remplace l'ENUM hardcodé dans la table biens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_types_transaction', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 50)->unique();
            $table->string('nom', 100);
            $table->string('description', 255)->nullable();

            // Indique si ce type implique un loyer récurrent (location/colocation)
            // → affiche les champs avance_mois, caution, unite_prix
            $table->boolean('est_location')->default(false);

            // Si true → le champ unite_prix est proposé
            $table->boolean('demande_unite_prix')->default(false);

            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['actif', 'ordre']);
        });

        // Libérer la colonne type_transaction de l'ENUM vers VARCHAR
        Schema::table('biens', function (Blueprint $table) {
            $table->string('type_transaction', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_types_transaction');
    }
};
