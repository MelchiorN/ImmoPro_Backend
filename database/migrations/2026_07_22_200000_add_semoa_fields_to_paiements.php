<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Ajout colonne semoa_bill_id + extension enum statut dans paiements
 * pour intégration Semoa CashPay API V2.0
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            // Identifiant de la facture Semoa (retourné par l'API)
            $table->string('semoa_bill_id')->nullable()->after('reference_transaction')
                  ->comment('Identifiant facture Semoa CashPay');
        });

        // Étendre l'enum statut pour ajouter 'confirme' et 'en_attente'
        DB::statement("ALTER TABLE paiements MODIFY COLUMN statut ENUM('initie','en_attente','confirme','succes','echoue') DEFAULT 'initie'");
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn('semoa_bill_id');
        });

        // Restaurer l'enum d'origine
        DB::statement("ALTER TABLE paiements MODIFY COLUMN statut ENUM('initie','succes','echoue') DEFAULT 'initie'");
    }
};
