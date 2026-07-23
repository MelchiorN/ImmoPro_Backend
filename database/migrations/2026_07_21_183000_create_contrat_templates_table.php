<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrats_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('titre')->default('Bail d\'habitation à usage résidentiel (Togo)');
            $table->text('description')->nullable();
            $table->string('type')->default('habitation');
            $table->longText('contenu_html');
            $table->boolean('est_actif')->default(true);
            $table->boolean('est_defaut')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrats_templates');
    }
};
