<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('conversation_id')->constrained('conversations')->onDelete('cascade');

            // Qui a envoyé ce message
            $table->foreignUuid('sender_id')->constrained('users')->onDelete('cascade');

            // Contenu du message
            $table->text('contenu');

            // Timestamp de lecture par le destinataire (null = non lu)
            $table->timestamp('lu_le')->nullable();

            $table->timestamps();

            $table->index('conversation_id');
            $table->index('sender_id');
            $table->index('lu_le');
            $table->index('created_at'); // pour le tri chronologique
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
