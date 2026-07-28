<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('essais_gratuits_restants')
                  ->default(1)
                  ->after('device_token')
                  ->comment('Quota de publications gratuites restantes. Initialisé depuis config_publication, surchargeable par l\'admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('essais_gratuits_restants');
        });
    }
};
