<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table de configuration des rôles déposant.
 * Remplace la constante ROLES_DEPOSANT et le switch($role) dans StoreBienRequest.
 *
 * Chaque rôle peut avoir :
 *   - Des champs personnels propres (config_champs_deposant)
 *   - Des documents requis spécifiques (config_docs_par_role)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Table principale des rôles ────────────────────────────────────────
        Schema::create('config_roles_deposant', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 50)->unique();
            $table->string('nom', 100);
            $table->string('description', 500)->nullable();

            // Si true : le déposant EST le propriétaire → pas de section "propriétaire réel"
            $table->boolean('est_proprietaire')->default(false);

            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['actif', 'ordre']);
        });

        // ── Champs personnels du propriétaire réel demandés selon le rôle ────
        Schema::create('config_champs_deposant', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_id')
                  ->constrained('config_roles_deposant')
                  ->onDelete('cascade');

            $table->string('nom_champ', 100);   // ex: proprietaire_nom
            $table->string('label', 150);        // ex: Nom du propriétaire
            $table->string('placeholder', 255)->nullable();

            // type: texte | email | telephone | enum | booleen
            $table->string('type_champ', 30)->default('texte');

            // Si type_champ = enum, liste des options JSON
            $table->json('options_enum')->nullable();

            $table->boolean('obligatoire')->default(true);
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['role_id', 'nom_champ']);
            $table->index(['role_id', 'actif', 'ordre']);
        });

        // ── Documents requis / optionnels par rôle ────────────────────────────
        Schema::create('config_docs_par_role', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_id')
                  ->constrained('config_roles_deposant')
                  ->onDelete('cascade');
            $table->foreignUuid('type_document_id')
                  ->constrained('config_types_document')
                  ->onDelete('cascade');

            $table->boolean('obligatoire')->default(true);
            $table->timestamps();

            $table->unique(['role_id', 'type_document_id']);
            $table->index('role_id');
        });

        // Libérer la colonne role_deposant de tout enum résiduel
        Schema::table('biens', function (Blueprint $table) {
            $table->string('role_deposant', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_docs_par_role');
        Schema::dropIfExists('config_champs_deposant');
        Schema::dropIfExists('config_roles_deposant');
    }
};
