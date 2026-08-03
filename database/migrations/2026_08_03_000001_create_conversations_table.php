<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Les deux partis de la conversation
            $table->foreignUuid('agent_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('client_id')->constrained('users')->onDelete('cascade');

            // Optionnel : lié à un bien spécifique (ex. demande d'info sur une annonce)
            $table->foreignUuid('bien_id')->nullable()->constrained('biens')->nullOnDelete();

            // Dernier message — pour trier les conversations (preview dans la liste)
            $table->timestamp('dernier_message_le')->nullable();

            $table->timestamps();

            // Une paire (agent, client) peut avoir plusieurs conversations (par bien différent)
            // mais une seule conversation sans bien ou pour le même bien
            $table->unique(['agent_id', 'client_id', 'bien_id']);

            $table->index('agent_id');
            $table->index('client_id');
            $table->index('dernier_message_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
