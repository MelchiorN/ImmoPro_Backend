<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrats_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('contrats_templates', 'description')) {
                $table->text('description')->nullable()->after('titre');
            }
            if (!Schema::hasColumn('contrats_templates', 'type')) {
                $table->string('type')->default('habitation')->after('description');
            }
            if (!Schema::hasColumn('contrats_templates', 'est_defaut')) {
                $table->boolean('est_defaut')->default(false)->after('est_actif');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contrats_templates', function (Blueprint $table) {
            if (Schema::hasColumn('contrats_templates', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('contrats_templates', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('contrats_templates', 'est_defaut')) {
                $table->dropColumn('est_defaut');
            }
        });
    }
};
