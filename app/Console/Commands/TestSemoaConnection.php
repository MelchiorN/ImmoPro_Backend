<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Payment\SemoaService;
use Illuminate\Support\Facades\Config;

class TestSemoaConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'semoa:test {--real : Forcer le test réel en ignorant le mode simulation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tester la connexion et l\'authentification à l\'API Semoa CashPay';

    /**
     * Execute the console command.
     */
    public function handle(SemoaService $semoaService): int
    {
        $this->info("=== Test de connexion Semoa CashPay API ===");
        
        $baseUrl = config('services.semoa.base_url');
        $username = config('services.semoa.username');
        $simulate = (bool) config('services.semoa.simulate', false);

        if ($this->option('real')) {
            config(['services.semoa.simulate' => false]);
            $simulate = false;
            $this->warn("Option --real activée : Mode simulation désactivé temporairement.");
        }

        $this->table(['Paramètre', 'Valeur'], [
            ['Base URL', $baseUrl],
            ['Nom d\'utilisateur', $username],
            ['Mode Simulation', $simulate ? 'OUI (Simulé en local)' : 'NON (Connexion réelle Sandbox)'],
        ]);

        $this->info("1. Test d'authentification (POST /auth)...");
        $result = $semoaService->testConnexion();

        if ($result['success']) {
            $this->info("✔ Authentification réussie !");
            $this->line("Aperçu du Token : " . ($result['token_preview'] ?? 'N/A'));

            $this->info("\n2. Récupération des passerelles de paiement (GET /gateways)...");
            try {
                $gateways = $semoaService->getGateways();
                $this->info("✔ Gateways récupérées (" . count($gateways) . " trouvées) :");
                
                $rows = [];
                foreach ($gateways as $gw) {
                    $rows[] = [
                        $gw['id'] ?? $gw['uuid'] ?? 'N/A',
                        $gw['name'] ?? $gw['label'] ?? 'N/A',
                        $gw['status'] ?? 'Active',
                    ];
                }
                if (!empty($rows)) {
                    $this->table(['UUID / Reference', 'Nom / Passerelle', 'Statut'], $rows);
                } else {
                    $this->line(json_encode($gateways, JSON_PRETTY_PRINT));
                }
            } catch (\Throwable $e) {
                $this->error("✘ Échec de la récupération des gateways : " . $e->getMessage());
            }

            return Command::SUCCESS;
        } else {
            $this->error("✘ Échec d'authentification : " . $result['message']);
            return Command::FAILURE;
        }
    }
}
