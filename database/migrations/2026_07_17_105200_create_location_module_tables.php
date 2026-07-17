<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1b. Modifier biens ──────────────────────────────────────────────
        Schema::table('biens', function (Blueprint $table) {
            // Prix affiché publiquement (avec commission incluse)
            $table->decimal('prix_public', 15, 2)
                  ->nullable()
                  ->after('prix')
                  ->comment('Prix avec commission ImmoPro incluse');

            // Verrouillage temporaire anti-concurrence pour les locations
            $table->timestamp('locked_until')
                  ->nullable()
                  ->after('publie_le')
                  ->comment('Verrou temporaire pour empêcher les locations concurrentes');
        });

        // ── 1c. Table locations ─────────────────────────────────────────────
        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bien_id')->constrained('biens')->onDelete('cascade');
            $table->foreignUuid('locataire_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('proprietaire_id')->constrained('users')->onDelete('cascade');

            $table->date('date_debut');
            $table->date('date_fin');
            $table->unsignedInteger('duree_mois');

            // Snapshots financiers au moment de la location
            $table->decimal('prix_proprietaire', 15, 2)->comment('Prix original du propriétaire (snapshot)');
            $table->decimal('montant_commission', 15, 2)->comment('Montant commission prélevé (snapshot)');
            $table->decimal('montant_total', 15, 2)->comment('Ce que le client paie au total');

            $table->enum('statut', [
                'en_attente_contrat',
                'en_attente_paiement',
                'actif',
                'termine',
                'annule',
            ])->default('en_attente_contrat');

            $table->timestamps();
            $table->index('statut');
        });

        // ── 1d. Table contrats ──────────────────────────────────────────────
        Schema::create('contrats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('location_id')->constrained('locations')->onDelete('cascade');

            $table->longText('contenu_html')->comment('Contenu brut du contrat (template HTML)');
            $table->string('fichier_pdf')->nullable()->comment('Chemin du fichier PDF stocké');

            $table->timestamp('date_generation');
            $table->timestamp('date_acceptation')->nullable();
            $table->enum('statut_signature', ['en_attente', 'signe'])->default('en_attente');

            $table->timestamps();
        });

        // ── 1e. Table paiements ─────────────────────────────────────────────
        Schema::create('paiements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('location_id')->constrained('locations')->onDelete('cascade');

            $table->decimal('montant', 15, 2);
            $table->string('operateur_paiement', 100)->comment('ex: Orange Money, Wave, MTN MoMo');
            $table->string('reference_transaction')->nullable()->comment('Référence retournée par l\'opérateur');
            $table->enum('statut', ['initie', 'succes', 'echoue'])->default('initie');

            $table->timestamps();
            $table->index('statut');
        });

        // ── 1f. Table recus ─────────────────────────────────────────────────
        Schema::create('recus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('paiement_id')->constrained('paiements')->onDelete('cascade');

            $table->string('numero_recu', 50)->unique()->comment('ex: REC-2026-0001');
            $table->string('fichier_pdf')->nullable()->comment('Chemin du fichier PDF stocké');
            $table->timestamp('date_emission');

            $table->timestamps();
        });

        // ── 1g. Table commissions ───────────────────────────────────────────
        Schema::create('commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignUuid('paiement_id')->constrained('paiements')->onDelete('cascade');

            $table->decimal('pourcentage_applique', 5, 2)->comment('Taux appliqué au moment de la transaction');
            $table->decimal('montant_gagne', 15, 2)->comment('Montant réel gagné par ImmoPro');
            $table->timestamp('date_prelevement');

            $table->timestamps();
        });

        // ── 1h. Table reversements ──────────────────────────────────────────
        Schema::create('reversements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('proprietaire_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('location_id')->constrained('locations')->onDelete('cascade');

            $table->decimal('montant_a_reverser', 15, 2)->comment('Prix propriétaire × durée');
            $table->enum('statut', ['en_attente', 'traite'])->default('en_attente');
            $table->timestamp('date_paiement')->nullable()->comment('Date du virement au propriétaire');

            $table->timestamps();
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reversements');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('recus');
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('contrats');
        Schema::dropIfExists('locations');

        Schema::table('biens', function (Blueprint $table) {
            $table->dropColumn(['prix_public', 'locked_until']);
        });
    }
};
