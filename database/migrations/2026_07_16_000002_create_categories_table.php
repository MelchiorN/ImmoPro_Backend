<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nom affiché à l'utilisateur (ex: "Maison / Villa")
            $table->string('nom', 100);

            // Slug technique correspondant à l'enum type_bien de la table biens
            // (appartement | maison | villa | terrain | bureau_commerce)
            // Unique : une catégorie par type de bien
            $table->string('slug', 50)->unique();

            // Description courte pour l'interface admin
            $table->text('description')->nullable();

            // L'admin peut désactiver une catégorie (elle n'apparaît plus dans le formulaire)
            $table->boolean('actif')->default(true);

            // Ordre d'affichage dans les listes / formulaires
            $table->unsignedTinyInteger('ordre_affichage')->default(0);

            $table->timestamps();

            $table->index('actif');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
