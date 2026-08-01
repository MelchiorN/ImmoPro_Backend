<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            // submitted_at : horodatage de soumission du dossier (étape 1 du workflow)
            if (! Schema::hasColumn('biens', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('publie_le');
            }
            // claimed_at : horodatage de prise en charge par l'agent (étape 2)
            if (! Schema::hasColumn('biens', 'claimed_at')) {
                $table->timestamp('claimed_at')->nullable()->after('submitted_at');
            }
            // sla1_alerted_at : date d'envoi de l'alerte SLA1 (prise en charge dépassée)
            if (! Schema::hasColumn('biens', 'sla1_alerted_at')) {
                $table->timestamp('sla1_alerted_at')->nullable()->after('claimed_at');
            }
            // sla2_alerted_at : date d'envoi de l'alerte SLA2 (rapport non rédigé)
            if (! Schema::hasColumn('biens', 'sla2_alerted_at')) {
                $table->timestamp('sla2_alerted_at')->nullable()->after('sla1_alerted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'claimed_at', 'sla1_alerted_at', 'sla2_alerted_at']);
        });
    }
};
