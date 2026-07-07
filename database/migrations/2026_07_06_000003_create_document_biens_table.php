<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_biens', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('bien_id')
                  ->constrained('biens')
                  ->onDelete('cascade');

            $table->enum('type', [
                'titre_foncier',
                'piece_identite',
                'plan_cadastral',
                'autre',
            ]);
            $table->string('chemin', 500);
            $table->string('nom_original', 255)->nullable(); // nom du fichier uploadé
            $table->unsignedBigInteger('taille')->nullable();
            $table->string('mime_type', 100)->nullable();

            $table->enum('statut', ['en_attente', 'valide', 'rejete'])->default('en_attente');
            $table->text('note')->nullable(); // commentaire de validation

            $table->timestamps();

            $table->index(['bien_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_biens');
    }
};
