<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creneaux_visite', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('bien_id')->constrained('biens')->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained('users')->cascadeOnDelete();

            $table->dateTime('date_debut');
            $table->dateTime('date_fin');   // = date_debut + duree_minutes

            $table->enum('statut', ['disponible', 'choisi', 'expire'])->default('disponible');

            // Renseigné quand le proprio choisit ce créneau → visite créée
            $table->foreignUuid('visite_id')->nullable()->constrained('visites')->nullOnDelete();

            $table->timestamps();

            $table->index(['bien_id', 'statut']);
            $table->index('date_debut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creneaux_visite');
    }
};
