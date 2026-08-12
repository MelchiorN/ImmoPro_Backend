<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout colonne payment_url dans paiements
 * Stocke la bill_url retournée par Semoa (ou construite en simulation)
 * pour pouvoir la renvoyer au client sans la reconstruire à la main.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('payment_url', 500)->nullable()->after('semoa_bill_id')
                  ->comment('URL de paiement retournée par Semoa (bill_url)');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn('payment_url');
        });
    }
};
