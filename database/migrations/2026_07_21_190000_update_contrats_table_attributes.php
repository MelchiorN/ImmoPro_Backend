<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            if (!Schema::hasColumn('contrats', 'url_pdf')) {
                $table->string('url_pdf')->nullable()->after('fichier_pdf');
            }
            if (!Schema::hasColumn('contrats', 'date_creation')) {
                $table->timestamp('date_creation')->nullable()->after('date_generation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            if (Schema::hasColumn('contrats', 'url_pdf')) {
                $table->dropColumn('url_pdf');
            }
            if (Schema::hasColumn('contrats', 'date_creation')) {
                $table->dropColumn('date_creation');
            }
        });
    }
};
