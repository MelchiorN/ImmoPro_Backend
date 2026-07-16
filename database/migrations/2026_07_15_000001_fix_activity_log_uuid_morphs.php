<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Corrige la table activity_log pour supporter les UUID comme causer_id et subject_id.
 * Par défaut, Spatie génère des colonnes unsignedBigInteger qui sont incompatibles
 * avec les UUID utilisés dans ce projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Vider la table avant migration (données de dev seulement)
        DB::table('activity_log')->truncate();

        // Récupérer les noms d'index existants (compatible SQLite et MySQL)
        if (DB::getDriverName() === 'sqlite') {
            $existingIndexes = collect(DB::select("PRAGMA index_list(`activity_log`)"))
                ->pluck('name')
                ->toArray();
        } else {
            $existingIndexes = collect(DB::select("SHOW INDEX FROM `activity_log`"))
                ->pluck('Key_name')
                ->unique()
                ->toArray();
        }

        $indexesToDrop = [
            'subject',
            'causer',
            'activity_log_subject_type_subject_id_index',
            'activity_log_causer_type_causer_id_index',
        ];

        Schema::table('activity_log', function (Blueprint $table) use ($existingIndexes, $indexesToDrop) {
            foreach ($indexesToDrop as $index) {
                if (in_array($index, $existingIndexes)) {
                    $table->dropIndex($index);
                }
            }
        });

        // Supprimer les colonnes une par une si elles existent
        $columnsToDrop = array_filter(
            ['subject_type', 'subject_id', 'causer_type', 'causer_id'],
            fn ($col) => Schema::hasColumn('activity_log', $col)
        );

        if (!empty($columnsToDrop)) {
            Schema::table('activity_log', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn(array_values($columnsToDrop));
            });
        }

        Schema::table('activity_log', function (Blueprint $table) {
            // Recréer avec string(36) pour les UUID
            $table->string('subject_type', 255)->nullable()->after('description');
            $table->string('subject_id', 36)->nullable()->after('subject_type');
            $table->string('causer_type', 255)->nullable()->after('subject_id');
            $table->string('causer_id', 36)->nullable()->after('causer_type');

            $table->index(['subject_type', 'subject_id'], 'subject');
            $table->index(['causer_type', 'causer_id'], 'causer');
        });
    }

    public function down(): void
    {
        DB::table('activity_log')->truncate();

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('subject');
            $table->dropIndex('causer');
            $table->dropColumn(['subject_type', 'subject_id', 'causer_type', 'causer_id']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
        });
    }
};
