<?php

namespace App\Console\Commands;

use App\Models\HistoriqueRecherche;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Commande de nettoyage de l'historique de recherche.
 *
 * Usage :
 *   php artisan historique:purger              → garde les 50 dernières par utilisateur
 *   php artisan historique:purger --garder=100  → garde les 100 dernières
 *   php artisan historique:purger --jours=90    → supprime les entrées de + de 90 jours
 *
 * À planifier dans app/Console/Kernel.php (ou routes/console.php) :
 *   Schedule::command('historique:purger')->weekly();
 */
class PurgerHistoriqueRecherche extends Command
{
    protected $signature = 'historique:purger
                            {--garder=50 : Nombre de recherches à conserver par utilisateur}
                            {--jours=180 : Supprimer les entrées de plus de N jours}';

    protected $description = 'Nettoie l\'historique de recherche : garde les N dernières entrées par utilisateur et supprime les anciennes.';

    public function handle(): int
    {
        $garder = (int) $this->option('garder');
        $jours  = (int) $this->option('jours');

        // ── Étape 1 : Supprimer les entrées trop anciennes ────────────────────
        $supprimeesAncien = HistoriqueRecherche::where('created_at', '<', now()->subDays($jours))->delete();
        $this->info("  → {$supprimeesAncien} entrée(s) supprimée(s) car antérieures à {$jours} jours.");

        // ── Étape 2 : Par utilisateur, ne garder que les N dernières ──────────
        $userIds = HistoriqueRecherche::select('user_id')->distinct()->pluck('user_id');
        $total   = 0;

        foreach ($userIds as $userId) {
            $idsAGarder = HistoriqueRecherche::where('user_id', $userId)
                ->latest()
                ->take($garder)
                ->pluck('id');

            $supprimees = HistoriqueRecherche::where('user_id', $userId)
                ->whereNotIn('id', $idsAGarder)
                ->delete();

            $total += $supprimees;
        }

        $this->info("  → {$total} entrée(s) supprimée(s) par dépassement de quota ({$garder} max/utilisateur).");
        $this->info('Purge terminée.');

        return Command::SUCCESS;
    }
}
