<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table de suppression "douce" — le message reste en BDD et visible par l'admin
        // mais est masqué pour l'utilisateur qui l'a supprimé (ou pour tous si suppression globale)
        Schema::create('message_suppressions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('message_id')->constrained('messages')->onDelete('cascade');

            // Qui a demandé la suppression
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');

            // false = "supprimer pour moi seulement"
            // true  = "supprimer pour tout le monde"
            $table->boolean('pour_tous')->default(false);

            $table->timestamp('supprime_le')->useCurrent();

            // Un utilisateur ne peut supprimer le même message qu'une seule fois
            $table->unique(['message_id', 'user_id']);

            $table->index('message_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_suppressions');
    }
};
