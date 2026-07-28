<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanAbonnementSeeder extends Seeder
{
    public function run(): void
    {
        // Éviter les doublons si le seeder est relancé
        DB::table('plan_abonnements')->delete();

        $plans = $this->plansData();

        foreach ($plans as $plan) {
            DB::table('plan_abonnements')->insert([
                'id'              => (string) Str::uuid(),
                'nom'             => $plan['nom'],
                'description'     => $plan['description'],
                'nb_publications' => $plan['nb_publications'],
                'prix'            => $plan['prix'],
                'ordre'           => $plan['ordre'],
                'est_actif'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        $this->command->info('✅ Plans d\'abonnement insérés avec succès (' . count($plans) . ' plans).');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Données de référence
    // ─────────────────────────────────────────────────────────────────────────

    private function plansData(): array
    {
        return [
            [
                'nom'             => 'Starter',
                'description'     => 'Idéal pour démarrer. Publiez vos premiers biens à prix réduit.',
                'nb_publications' => 3,
                'prix'            => 5000.00,  // 5 000 FCFA
                'ordre'           => 1,
            ],
            [
                'nom'             => 'Standard',
                'description'     => 'Le plan le plus populaire. Parfait pour les propriétaires actifs.',
                'nb_publications' => 5,
                'prix'            => 8000.00,  // 8 000 FCFA
                'ordre'           => 2,
            ],
            [
                'nom'             => 'Pro',
                'description'     => 'Pour les agences et mandataires avec plusieurs biens à gérer.',
                'nb_publications' => 10,
                'prix'            => 14000.00, // 14 000 FCFA
                'ordre'           => 3,
            ],
            [
                'nom'             => 'Premium',
                'description'     => 'Accès illimité pour les professionnels de l\'immobilier.',
                'nb_publications' => 20,
                'prix'            => 25000.00, // 25 000 FCFA
                'ordre'           => 4,
            ],
        ];
    }
}
