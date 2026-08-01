<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table de configuration des unités de prix (par jour, par mois…).
 * Remplace la constante UNITES_PRIX hardcodée dans Bien.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_unites_prix', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 50)->unique();
            $table->string('nom', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['actif', 'ordre']);
        });

        // Libérer la colonne unite_prix de toute contrainte enum résiduelle
        Schema::table('biens', function (Blueprint $table) {
            $table->string('unite_prix', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_unites_prix');
    }
};
