<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute sur la table biens :
 *  - Identité du déposant (role_deposant + infos propriétaire réel)
 *  - Conditions de prix (unite_prix, avance_mois, caution)
 *  - Statut frais d'étude (frais_etude_statut, frais_etude_paiement_id)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biens', function (Blueprint $table) {

            // ── Identité du déposant ──────────────────────────────────────────
            $table->string('role_deposant', 50)->nullable()->after('statut')
                  ->comment('proprietaire | agence | mandataire | heritier | autre');

            // Informations du propriétaire réel (si déposant ≠ propriétaire)
            $table->string('proprietaire_nom')->nullable()->after('role_deposant');
            $table->string('proprietaire_prenom')->nullable()->after('proprietaire_nom');
            $table->string('proprietaire_sexe', 10)->nullable()->after('proprietaire_prenom')
                  ->comment('homme | femme');
            $table->string('proprietaire_nationalite')->nullable()->after('proprietaire_sexe');
            $table->string('proprietaire_telephone', 30)->nullable()->after('proprietaire_nationalite');
            $table->string('proprietaire_email')->nullable()->after('proprietaire_telephone');
            $table->string('proprietaire_adresse')->nullable()->after('proprietaire_email');

            // ── Conditions de prix ────────────────────────────────────────────
            $table->string('unite_prix', 20)->nullable()->after('prix_public')
                  ->comment('jour | semaine | mois | annee');
            $table->unsignedTinyInteger('avance_mois')->nullable()->after('unite_prix')
                  ->comment('Nombre de mois d\'avance (location seulement)');
            $table->decimal('caution', 15, 2)->nullable()->after('avance_mois')
                  ->comment('Montant de la caution (location seulement)');

            // ── Frais d\'étude ─────────────────────────────────────────────────
            $table->string('frais_etude_statut', 20)->default('non_requis')->after('locked_until')
                  ->comment('non_requis | en_attente_paiement | paye');
            $table->uuid('frais_etude_paiement_id')->nullable()->after('frais_etude_statut')
                  ->comment('FK vers paiements.id pour le frais d\'étude');
        });
    }

    public function down(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            $table->dropColumn([
                'role_deposant',
                'proprietaire_nom', 'proprietaire_prenom', 'proprietaire_sexe',
                'proprietaire_nationalite', 'proprietaire_telephone',
                'proprietaire_email', 'proprietaire_adresse',
                'unite_prix', 'avance_mois', 'caution',
                'frais_etude_statut', 'frais_etude_paiement_id',
            ]);
        });
    }
};
