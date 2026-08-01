<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `documents_legaux`
 *
 * Stocke les documents légaux paramétrables depuis l'administration :
 *   - CGU  (Conditions Générales d'Utilisation)
 *   - CGV  (Conditions Générales de Vente)
 *   - PC   (Politique de Confidentialité)
 *   - AP   (À propos de ImmoPro)
 *
 * Chaque document est unique par `slug` (upsert côté application).
 * Le contenu est du texte riche (markdown ou HTML) stocké en LONGTEXT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_legaux', function (Blueprint $table) {
            $table->id();

            // Identifiant métier unique (cgu, cgv, politique_confidentialite, a_propos)
            $table->string('slug', 50)->unique();

            // Titre affiché sur le mobile et dans l'admin
            $table->string('titre', 255);

            // Description courte (optionnelle, affichée dans le résumé admin)
            $table->string('description', 500)->nullable();

            // Contenu complet (Markdown ou HTML)
            $table->longText('contenu');

            // Contrôle de visibilité côté mobile
            $table->boolean('actif')->default(true);

            // Version incrémentée à chaque mise à jour (utile pour le cache mobile)
            $table->unsignedInteger('version')->default(1);

            // Dates de mise à jour éditoriale (différent de updated_at Eloquent)
            $table->timestamp('date_maj')->nullable()->comment('Date de la dernière modification éditoriale');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_legaux');
    }
};
