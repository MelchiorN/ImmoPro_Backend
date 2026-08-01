<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rend date_visite nullable dans la table visites.
 *
 * Contexte : lors du paiement des frais de visite (confirmerPaiement), une visite
 * est créée avec statut 'proposee' — la date réelle n'est définie que plus tard,
 * quand l'agent propose des créneaux et que le client en choisit un.
 * La colonne était NOT NULL depuis la migration initiale, ce qui provoque une
 * erreur SQL 1364 "Field 'date_visite' doesn't have a default value".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visites', function (Blueprint $table) {
            $table->dateTime('date_visite')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Remettre NOT NULL — attention : les lignes avec date_visite = NULL
        // devront être traitées avant d'appliquer ce rollback.
        Schema::table('visites', function (Blueprint $table) {
            $table->dateTime('date_visite')->nullable(false)->change();
        });
    }
};
