<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rapports', function (Blueprint $table) {
            $table->timestamp('soumis_le')->nullable()->after('note_finale');
            $table->text('note_rejet')->nullable()->after('soumis_le');
        });

        // Modifier l'enum pour ajouter "rejete"
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE rapports MODIFY COLUMN statut ENUM(
                'brouillon', 'soumis', 'valide', 'rejete'
            ) NOT NULL DEFAULT 'brouillon'");
        }
        // SQLite : pas nécessaire
    }

    public function down(): void
    {
        Schema::table('rapports', function (Blueprint $table) {
            $table->dropColumn(['soumis_le', 'note_rejet']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE rapports MODIFY COLUMN statut ENUM(
                'brouillon', 'soumis', 'valide'
            ) NOT NULL DEFAULT 'brouillon'");
        }
    }
};
