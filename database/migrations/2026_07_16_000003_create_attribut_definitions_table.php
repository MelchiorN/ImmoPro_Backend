<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribut_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Catégorie parente
            $table->foreignUuid('categorie_id')
                  ->constrained('categories')
                  ->onDelete('cascade');

            // Clé technique utilisée dans le JSON caracteristiques (ex: "nb_chambres")
            $table->string('nom_champ', 100);

            // Label affiché à l'utilisateur dans le formulaire (ex: "Nombre de chambres")
            $table->string('label_affiche', 150);

            // Type de données — détermine le composant de saisie généré
            $table->enum('type_champ', ['texte', 'nombre', 'booleen', 'enum', 'date'])
                  ->default('texte');

            // Pour type_champ = 'enum' : liste des options possibles
            // Format JSON : ["neuf", "bon_etat", "a_renover"]
            $table->json('options_enum')->nullable();

            // Champ obligatoire lors de la publication ?
            $table->boolean('obligatoire')->default(false);

            // Champ socle = défini par le système, non supprimable par l'admin
            // (ex: Nombre de chambres pour Appartement)
            $table->boolean('est_socle')->default(false);

            // L'admin peut masquer un champ existant sans le supprimer
            // Les annonces qui l'utilisent continuent à l'afficher en lecture seule
            $table->boolean('actif')->default(true);

            // Ordre dans le formulaire de publication
            $table->unsignedSmallInteger('ordre_affichage')->default(0);

            $table->timestamps();

            // Un nom de champ doit être unique par catégorie
            $table->unique(['categorie_id', 'nom_champ']);

            $table->index(['categorie_id', 'actif']);
            $table->index(['categorie_id', 'ordre_affichage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribut_definitions');
    }
};
