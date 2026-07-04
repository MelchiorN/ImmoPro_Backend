<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type');                     // ex : "nouvelle_offre", "otp", "alerte_prix"
            $table->string('titre');
            $table->text('message');
            $table->json('data')->nullable();           // données contextuelles (id bien, etc.)
            $table->boolean('lu')->default(false);
            $table->enum('canal', ['push', 'email', 'sms'])->default('push');
            $table->timestamp('lu_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'lu']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
