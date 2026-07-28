<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Rend la table paiements polymorphique pour supporter :
 *  - location     (paiement de loyer)
 *  - abonnement   (achat d'un plan de publication)
 *  - frais_etude  (frais d'étude de dossier avant soumission d'un bien)
 *
 * La colonne location_id existante est conservée pour rétro-compatibilité
 * puis rendue nullable. Les nouvelles lignes utilisent payable_type/payable_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            // Type de paiement
            $table->enum('type_paiement', ['location', 'abonnement', 'frais_etude'])
                  ->default('location')
                  ->after('id')
                  ->comment('Détermine la nature du paiement');

            // Relation polymorphique — remplace location_id pour les nouveaux types
            $table->string('payable_type')->nullable()
                  ->after('type_paiement')
                  ->comment('Classe du modèle lié : Location, UserAbonnement, Bien');

            $table->uuid('payable_id')->nullable()
                  ->after('payable_type')
                  ->comment('UUID de l\'entité liée');

            // Rendre location_id nullable (les paiements abonnement/frais_etude n'en ont pas)
            $table->foreignUuid('location_id')->nullable()->change();

            $table->index(['payable_type', 'payable_id']);
        });

        // Migrer les données existantes : renseigner payable_type/payable_id depuis location_id
        DB::statement("
            UPDATE paiements
            SET payable_type = 'App\\\\Models\\\\Location',
                payable_id   = location_id
            WHERE location_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropIndex(['payable_type', 'payable_id']);
            $table->dropColumn(['type_paiement', 'payable_type', 'payable_id']);
            $table->foreignUuid('location_id')->nullable(false)->change();
        });
    }
};
