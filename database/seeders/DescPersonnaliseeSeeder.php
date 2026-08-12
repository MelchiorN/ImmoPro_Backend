<?php

namespace Database\Seeders;

use App\Models\Bien;
use App\Services\BienDescriptionService;
use App\Services\GeminiService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeder : génère et stocke la desc_personnalisee (Gemini) pour tous les biens
 * déjà en statut "publie" ou "valide" qui n'ont pas encore de description personnalisée.
 *
 * Usage :
 *   php artisan db:seed --class=DescPersonnaliseeSeeder
 *
 * Respecte les quotas Gemini :
 *   - Pause de 3 secondes entre chaque appel (free tier : ~15 req/min)
 *   - En cas d'erreur 429 (quota), pause de 60 secondes avant de continuer
 *   - En cas d'échec persistant, log l'erreur et passe au bien suivant
 */
class DescPersonnaliseeSeeder extends Seeder
{
    public function __construct(
        private readonly GeminiService          $gemini,
        private readonly BienDescriptionService $descService,
    ) {}

    public function run(): void
    {
        // Récupérer les biens publiés ou validés sans description personnalisée
        $biens = Bien::whereIn('statut', ['publie', 'valide'])
            ->whereNull('desc_personnalisee')
            ->orderBy('publie_le', 'desc')
            ->get();

        $total   = $biens->count();
        $success = 0;
        $echecs  = 0;

        $this->command->info("🔍 {$total} bien(s) à traiter...");

        if ($total === 0) {
            $this->command->info('✅ Tous les biens ont déjà une description personnalisée.');
            return;
        }

        $bar = $this->command->getOutput()->createProgressBar($total);
        $bar->start();

        foreach ($biens as $bien) {
            try {
                $descBrute         = $this->descService->construire($bien);
                $descPersonnalisee = $this->gemini->enrichirDescription($descBrute, $bien->toArray());

                $bien->update(['desc_personnalisee' => $descPersonnalisee]);
                $success++;

                $bar->advance();

                // Pause pour respecter le free tier Gemini (≈15 req/min)
                sleep(4);

            } catch (\RuntimeException $e) {
                $message = $e->getMessage();

                // Quota dépassé → pause plus longue
                if (str_contains($message, '429') || str_contains($message, 'Quota')) {
                    $bar->clear();
                    $this->command->warn("\n⏳ Quota Gemini atteint. Pause 60 secondes...");
                    sleep(60);

                    // Retry une fois
                    try {
                        $descBrute         = $this->descService->construire($bien);
                        $descPersonnalisee = $this->gemini->enrichirDescription($descBrute, $bien->toArray());
                        $bien->update(['desc_personnalisee' => $descPersonnalisee]);
                        $success++;
                        $bar->advance();
                        sleep(4);
                        continue;
                    } catch (\Throwable) {
                        // Échoue aussi après retry → on passe au suivant
                    }
                }

                $echecs++;
                Log::warning('[DescPersonnaliseeSeeder] Échec pour bien ' . $bien->id, [
                    'titre' => $bien->titre,
                    'error' => $message,
                ]);
                $bar->advance();
            } catch (\Throwable $e) {
                $echecs++;
                Log::error('[DescPersonnaliseeSeeder] Erreur inattendue bien ' . $bien->id, [
                    'error' => $e->getMessage(),
                ]);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->command->newLine(2);
        $this->command->info("✅ Terminé : {$success} générées, {$echecs} échouées sur {$total}.");

        if ($echecs > 0) {
            $this->command->warn("⚠️  Relancez le seeder pour les {$echecs} biens restants.");
        }
    }
}
