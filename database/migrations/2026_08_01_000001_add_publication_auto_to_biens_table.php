<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            // true  = publier automatiquement apres validation agent
            // false = le proprietaire publie manuellement quand il veut
            $table->boolean('publication_auto')->default(true)->after('publie_le');
        });
    }

    public function down(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            $table->dropColumn('publication_auto');
        });
    }
};
