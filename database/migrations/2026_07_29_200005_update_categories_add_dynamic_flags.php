<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute sur la table categories :
 *  - a_chambres              : remplace typeSansChambres() — si false, nb_pieces/nb_salles_bain sont masqués
 *  - a_superficie_terrain    : si true, affiche le 2ème champ superficie (terrain, maison, villa)
 *  - documents_optionnels    : JSON de slugs de types_document optionnels spécifiques à cette catégorie
 *                              (ex: plan_cadastral pour terrain)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('a_chambres')
                  ->default(true)
                  ->after('frais_etude_pourcentage')
                  ->comment('Si false, nb_pieces/nb_salles_bain ne sont pas demandés');

            $table->boolean('a_superficie_terrain')
                  ->default(false)
                  ->after('a_chambres')
                  ->comment('Si true, affiche le champ superficie du terrain');

            // JSON array de slugs config_types_document optionnels pour cette catégorie
            $table->json('documents_optionnels')
                  ->nullable()
                  ->after('a_superficie_terrain')
                  ->comment('Liste de slugs de documents optionnels spécifiques à ce type de bien');
        });

        // Mettre à jour les catégories existantes selon leur nature
        // types sans chambres
        \Illuminate\Support\Facades\DB::table('categories')
            ->whereIn('slug', ['terrain', 'bureau', 'commerce', 'entrepot'])
            ->update(['a_chambres' => false]);

        // types avec superficie terrain
        \Illuminate\Support\Facades\DB::table('categories')
            ->whereIn('slug', ['terrain', 'maison', 'villa'])
            ->update(['a_superficie_terrain' => true]);

        // Terrain → plan_cadastral optionnel
        \Illuminate\Support\Facades\DB::table('categories')
            ->where('slug', 'terrain')
            ->update(['documents_optionnels' => json_encode(['plan_cadastral'])]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['a_chambres', 'a_superficie_terrain', 'documents_optionnels']);
        });
    }
};
