<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_abonnements', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('plan_id')->constrained('plan_abonnements')->onDelete('cascade');

            $table->unsignedInteger('nb_publications_initiales')->comment('Snapshot du nb_publications du plan au moment de l\'achat');
            $table->unsignedInteger('nb_publications_restantes')->comment('Solde restant, décrémenté à chaque soumission validée');

            $table->enum('statut', ['actif', 'epuise', 'annule'])->default('actif');

            $table->timestamp('date_achat');

            $table->timestamps();

            $table->index(['user_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_abonnements');
    }
};
