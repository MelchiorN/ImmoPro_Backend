<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historique_recherches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // Texte libre saisi dans la barre de recherche
            $table->string('query_text', 200)->nullable();

            // Filtres appliqués lors de la recherche
            $table->string('type_bien', 100)->nullable();
            $table->string('type_transaction', 50)->nullable();
            $table->decimal('prix_min', 15, 2)->nullable();
            $table->decimal('prix_max', 15, 2)->nullable();
            $table->string('ville', 100)->nullable();

            // Coordonnées GPS de la recherche (si l'utilisateur a utilisé le filtre géo)
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // Nombre de résultats obtenus (signal de pertinence de la recherche)
            $table->unsignedSmallInteger('nb_resultats')->default(0);

            $table->timestamps();

            // Index pour les requêtes fréquentes
            $table->index(['user_id', 'created_at']);
            $table->index('ville');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historique_recherches');
    }
};
