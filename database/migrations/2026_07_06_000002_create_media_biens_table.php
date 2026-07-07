<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_biens', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('bien_id')
                  ->constrained('biens')
                  ->onDelete('cascade');

            $table->enum('type', ['photo', 'video']);
            $table->string('chemin', 500);         // chemin relatif storage/app/public
            $table->string('url')->nullable();     // URL publique calculée
            $table->boolean('est_principale')->default(false);
            $table->unsignedTinyInteger('ordre')->default(0);
            $table->unsignedBigInteger('taille')->nullable(); // en octets
            $table->string('mime_type', 100)->nullable();

            $table->timestamps();

            $table->index(['bien_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_biens');
    }
};
