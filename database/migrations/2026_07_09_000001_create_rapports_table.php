<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bien_id')->constrained('biens')->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained('users')->cascadeOnDelete();
            $table->string('titre')->nullable();
            $table->text('contenu')->nullable();
            $table->enum('statut', ['brouillon', 'soumis', 'valide'])->default('brouillon');
            $table->json('checklist')->nullable(); // Points vérifiés
            $table->text('note_finale')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports');
    }
};
