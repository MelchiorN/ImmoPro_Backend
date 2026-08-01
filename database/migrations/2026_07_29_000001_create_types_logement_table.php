<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table types_logement pour rendre les types d'appartement
 * (Studio/F1, F2, F3, F4+) configurables dynamiquement depuis l'administration.
 *
 * Un admin peut ajouter, modifier, réordonner ou désactiver les types
 * sans toucher au code ni au seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_logement', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Catégorie parente (appartement, ou autre si besoin futur)
            $table->foreignUuid('categorie_id')
                  ->constrained('categories')
                  ->onDelete('cascade');

            // Clé technique envoyée dans l'API / stockée en DB
            $table->string('slug', 50);

            // Label affiché dans le formulaire de publication
            $table->string('nom', 100);

            // Description courte (optionnel)
            $table->string('description', 255)->nullable();

            // Champ socle = défini par le système, ne peut pas être supprimé par l'admin
            $table->boolean('est_socle')->default(true);

            // L'admin peut masquer un type sans le supprimer
            $table->boolean('actif')->default(true);

            // Ordre d'affichage dans le dropdown du formulaire
            $table->unsignedSmallInteger('ordre')->default(0);

            $table->timestamps();

            // Un slug unique par catégorie
            $table->unique(['categorie_id', 'slug']);
            $table->index(['categorie_id', 'actif', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_logement');
    }
};
