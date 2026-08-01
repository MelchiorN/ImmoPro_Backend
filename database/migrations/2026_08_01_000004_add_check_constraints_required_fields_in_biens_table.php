<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Ajoute des contraintes CHECK sur la table biens pour documenter
 * et enforcer au niveau DB que les champs "nullable" ne le sont
 * QUE pour les brouillons.
 *
 * Règle métier encodée en SQL :
 *   Si statut != 'brouillon' → titre, prix, adresse, latitude,
 *   longitude, type_bien, type_transaction doivent être NON NULL.
 *
 * Cela protège contre :
 *  - Des bugs backend qui oublieraient de valider avant soumission
 *  - Des insertions directes en DB qui contourneraient l'API
 *  - Une confusion du schéma (un dev sait que le NULL est contrôlé)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Une seule contrainte CHECK couvre tous les champs requis hors brouillon.
        // Logique : statut = 'brouillon' OU (tous les champs requis sont présents)
        DB::statement("
            ALTER TABLE biens
            ADD CONSTRAINT chk_biens_required_if_not_brouillon CHECK (
                statut = 'brouillon'
                OR (
                    titre           IS NOT NULL
                    AND prix            IS NOT NULL
                    AND adresse         IS NOT NULL
                    AND latitude        IS NOT NULL
                    AND longitude       IS NOT NULL
                    AND type_bien       IS NOT NULL
                    AND type_transaction IS NOT NULL
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE biens DROP CHECK chk_biens_required_if_not_brouillon");
    }
};
