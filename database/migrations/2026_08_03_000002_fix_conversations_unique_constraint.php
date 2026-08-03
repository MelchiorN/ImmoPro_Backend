<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Modifie la contrainte unique sur la table conversations :
 *   AVANT : unique(agent_id, client_id, bien_id)
 *   APRÈS : unique(agent_id, client_id)
 *
 * Une seule conversation par paire agent/client, quel que soit le bien.
 * Le bien_id reste sur la conversation (info du bien du premier contact),
 * mais ne discrimine plus la création.
 *
 * Les doublons existants (même agent+client, biens différents) sont fusionnés :
 * on garde la conversation la plus récente et on rattache tous ses messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Fusionner les conversations en doublon (même agent+client) ─────
        // Pour chaque groupe (agent_id, client_id) ayant plusieurs conversations,
        // on garde la plus récente et on réaffecte les messages des autres.
        $doublons = DB::table('conversations')
            ->select('agent_id', 'client_id', DB::raw('COUNT(*) as nb'), DB::raw('MAX(created_at) as max_created'))
            ->groupBy('agent_id', 'client_id')
            ->having('nb', '>', 1)
            ->get();

        foreach ($doublons as $doublon) {
            // Toutes les conversations de ce couple, triées : la plus récente en premier
            $convs = DB::table('conversations')
                ->where('agent_id', $doublon->agent_id)
                ->where('client_id', $doublon->client_id)
                ->orderByDesc('dernier_message_le')
                ->orderByDesc('created_at')
                ->get();

            $keeper = $convs->first(); // On garde celle-ci

            foreach ($convs->slice(1) as $conv) {
                // Rattacher les messages de la conv supprimée vers la conv gardée
                DB::table('messages')
                    ->where('conversation_id', $conv->id)
                    ->update(['conversation_id' => $keeper->id]);

                // Supprimer la conv en doublon
                DB::table('conversations')->where('id', $conv->id)->delete();
            }

            // Mettre à jour dernier_message_le si nécessaire
            $dernierMsg = DB::table('messages')
                ->where('conversation_id', $keeper->id)
                ->orderByDesc('created_at')
                ->first();

            if ($dernierMsg) {
                DB::table('conversations')
                    ->where('id', $keeper->id)
                    ->update(['dernier_message_le' => $dernierMsg->created_at]);
            }
        }

        // ── 2. Modifier la contrainte unique ──────────────────────────────────
        Schema::table('conversations', function (Blueprint $table) {
            // Supprimer l'ancienne contrainte unique (agent_id, client_id, bien_id)
            $table->dropUnique(['agent_id', 'client_id', 'bien_id']);

            // Ajouter la nouvelle contrainte unique (agent_id, client_id) seulement
            $table->unique(['agent_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['agent_id', 'client_id']);
            $table->unique(['agent_id', 'client_id', 'bien_id']);
        });
    }
};
