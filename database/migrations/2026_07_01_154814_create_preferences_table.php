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
        Schema::create('preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade')->unique();
            $table->boolean('notifications_email')->default(true);
            $table->boolean('notifications_push')->default(true);
            $table->boolean('notifications_sms')->default(false);
            $table->string('langue', 10)->default('fr');
            $table->string('devise', 10)->default('XOF');
            $table->json('types_biens_preferes')->nullable();   // ["appartement","villa"]
            $table->json('villes_preferees')->nullable();
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preferences');
    }
};
