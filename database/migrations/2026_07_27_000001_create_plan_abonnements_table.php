<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_abonnements', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('nom', 100)->comment('Ex: Starter, Pro, Premium');
            $table->text('description')->nullable();
            $table->unsignedInteger('nb_publications')->comment('Nombre de publications incluses dans ce plan');
            $table->decimal('prix', 15, 2)->comment('Prix d\'achat du plan');
            $table->unsignedInteger('ordre')->default(0)->comment('Ordre d\'affichage dans la liste');
            $table->boolean('est_actif')->default(true)->comment('Visible et achetable par les utilisateurs');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_abonnements');
    }
};
