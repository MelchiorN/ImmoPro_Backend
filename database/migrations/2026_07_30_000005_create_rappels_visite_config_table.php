<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rappels_visite_config', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->unsignedSmallInteger('valeur')->default(1);
            $table->enum('unite', ['minutes', 'heures', 'jours', 'semaines'])->default('jours');
            $table->boolean('est_jour_j')->default(false);  // cas "le jour même"
            $table->time('heure_jour_j')->nullable();        // heure d'envoi si est_jour_j
            $table->boolean('actif')->default(true);
            $table->tinyInteger('ordre')->default(0);
            $table->timestamps();
        });

        // Seed initial : 7j, 2j, veille (1j), jour J à 08h00
        DB::table('rappels_visite_config')->insert([
            ['valeur' => 7, 'unite' => 'jours',  'est_jour_j' => false, 'heure_jour_j' => null,    'actif' => true, 'ordre' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['valeur' => 2, 'unite' => 'jours',  'est_jour_j' => false, 'heure_jour_j' => null,    'actif' => true, 'ordre' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['valeur' => 1, 'unite' => 'jours',  'est_jour_j' => false, 'heure_jour_j' => null,    'actif' => true, 'ordre' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['valeur' => 0, 'unite' => 'jours',  'est_jour_j' => true,  'heure_jour_j' => '08:00', 'actif' => true, 'ordre' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rappels_visite_config');
    }
};
