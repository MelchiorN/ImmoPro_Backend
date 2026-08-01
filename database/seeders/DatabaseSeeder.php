<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\CategorieSeeder;
use Database\Seeders\ConfigPublicationSeeder;
use Database\Seeders\DocumentLegalSeeder;
use Database\Seeders\PlanAbonnementSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            // UserSeeder::class,
            // CategorieSeeder::class,
            // ConfigPublicationSeeder::class,  // Types transaction, unités prix, types docs, rôles déposant
            // BienSeeder::class,
            // ContratTemplateSeeder::class,
            // PlanAbonnementSeeder::class,
            DocumentLegalSeeder::class,     // CGU, CGV, Politique de confidentialité, À propos
        ]);
    }
}
