<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visites', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('bien_id')
                  ->constrained('biens')
                  ->onDelete('cascade');

            $table->foreignUuid('agent_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->dateTime('date_visite');
            $table->text('notes')->nullable();

            // Statuts : planifiee | confirmee | annulee | rapport_soumis
            $table->enum('statut', ['planifiee', 'confirmee', 'annulee', 'rapport_soumis'])
                  ->default('planifiee');

            // Rapport post-visite
            $table->text('rapport')->nullable();
            $table->boolean('visite_effectuee')->nullable();

            $table->timestamps();

            $table->index(['bien_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visites');
    }
};
