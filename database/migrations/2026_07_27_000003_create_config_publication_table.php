<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_publication', function (Blueprint $table) {
            $table->id(); // Toujours 1 seule ligne

            $table->unsignedInteger('essais_gratuits_defaut')->default(1)
                  ->comment('Nombre de publications gratuites accordées à tout nouvel utilisateur');

            $table->decimal('frais_etude_dossier', 15, 2)->default(0)
                  ->comment('Montant des frais d\'étude payés avant soumission d\'un bien');

            $table->boolean('frais_etude_actifs')->default(false)
                  ->comment('Activer ou désactiver la collecte des frais d\'étude');

            $table->timestamps();
        });

        // Insérer la ligne de configuration par défaut
        DB::table('config_publication')->insert([
            'id'                     => 1,
            'essais_gratuits_defaut' => 1,
            'frais_etude_dossier'    => 0,
            'frais_etude_actifs'     => false,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('config_publication');
    }
};
