<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute delivre_le sur la table messages.
 *
 * delivre_le = moment où le message a été reçu en temps réel
 *              par le destinataire (broadcast Reverb ACK).
 *
 * null          → envoyé (sauvegardé en BDD)
 * non null      → délivré (destinataire était connecté, a reçu le push)
 * lu_le non nul → lu (destinataire a ouvert la conversation)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('delivre_le')->nullable()->after('lu_le');
            $table->index('delivre_le');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['delivre_le']);
            $table->dropColumn('delivre_le');
        });
    }
};
