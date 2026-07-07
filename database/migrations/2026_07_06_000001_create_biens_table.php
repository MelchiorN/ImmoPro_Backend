<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biens', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Propriétaire
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');

            // Classification
            $table->enum('type_bien', [
                'appartement',
                'maison',
                'villa',
                'terrain',
                'bureau_commerce',
            ]);
            $table->enum('type_transaction', ['vente', 'location', 'colocation']);

            // Infos de base
            $table->string('titre', 255);
            $table->text('description')->nullable();
            $table->decimal('prix', 15, 2);
            $table->decimal('surface', 8, 2)->nullable();

            // Pièces — NULL pour terrain/bureau simple
            $table->unsignedTinyInteger('nb_pieces')->nullable();
            $table->unsignedTinyInteger('nb_salles_bain')->nullable();

            // Localisation
            $table->string('adresse', 500);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            // Statut de publication
            $table->enum('statut', [
                'brouillon',
                'en_attente',
                'publie',
                'rejete',
                'archive',
            ])->default('en_attente');

            // Modération
            $table->text('note_admin')->nullable();       // motif rejet
            $table->foreignUuid('agent_id')->nullable()   // agent assigné
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('publie_le')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index pour les requêtes fréquentes
            $table->index('statut');
            $table->index('type_bien');
            $table->index('type_transaction');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biens');
    }
};
