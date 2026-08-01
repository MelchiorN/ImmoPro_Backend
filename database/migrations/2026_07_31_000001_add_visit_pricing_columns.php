<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter les colonnes de tarification à la table categories
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'visite_tarif_type')) {
                $table->enum('visite_tarif_type', ['pourcentage', 'fixe_manuel'])
                      ->default('fixe_manuel')
                      ->after('frais_etude_pourcentage');
            }
            if (!Schema::hasColumn('categories', 'visite_pourcentage')) {
                $table->decimal('visite_pourcentage', 5, 2)
                      ->nullable()
                      ->after('visite_tarif_type');
            }
            if (!Schema::hasColumn('categories', 'visite_tarif_fixe')) {
                $table->decimal('visite_tarif_fixe', 15, 2)
                      ->nullable()
                      ->after('visite_pourcentage');
            }
        });

        // 2. Ajouter le tarif final calculé/saisi à la table biens
        Schema::table('biens', function (Blueprint $table) {
            if (!Schema::hasColumn('biens', 'prix_visite')) {
                $table->decimal('prix_visite', 15, 2)
                      ->nullable()
                      ->after('prix_public')
                      ->comment('Tarif de la visite calculé ou saisi par admin/agent');
            }
        });

        // 3. Ajouter client_id et est_payee à la table visites
        Schema::table('visites', function (Blueprint $table) {
            if (!Schema::hasColumn('visites', 'client_id')) {
                $table->foreignUuid('client_id')
                      ->nullable()
                      ->after('agent_id')
                      ->constrained('users')
                      ->onDelete('cascade');
            }
            if (!Schema::hasColumn('visites', 'est_payee')) {
                $table->boolean('est_payee')
                      ->default(false)
                      ->after('visite_effectuee');
            }
            
            // Modifier la colonne agent_id pour être nullable dans le cas d'une visite client pas encore assignée à un agent
            $table->foreignUuid('agent_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('visites', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn(['client_id', 'est_payee']);
            // Restaurer la contrainte non-nullable pour agent_id si nécessaire
            $table->foreignUuid('agent_id')->nullable(false)->change();
        });

        Schema::table('biens', function (Blueprint $table) {
            $table->dropColumn(['prix_visite']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['visite_tarif_type', 'visite_pourcentage', 'visite_tarif_fixe']);
        });
    }
};
