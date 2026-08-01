<?php

namespace Database\Seeders;

use App\Models\Bien;
use App\Models\Paiement;
use App\Models\Recu;
use App\Models\User;
use App\Models\Visite;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder VisiteClientSeeder
 *
 * Crée une visite client pour omm94174@gmail.com sur un bien publié,
 * avec des créneaux par défaut proposés par l'agent (modifiables).
 *
 * Flux correct : agent propose créneaux → client choisit l'un d'eux.
 *
 * Lancer : php artisan db:seed --class=VisiteClientSeeder
 */
class VisiteClientSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Trouver ou créer le client ────────────────────────────────────
        $client = User::where('email', 'ommm94174@gmail.com')->first();

        if (! $client) {
            $this->command->warn('Client ommm94174@gmail.com introuvable — création du compte...');
            $client = User::create([
                'first_name' => 'Oma',
                'last_name'  => 'Client',
                'email'      => 'ommm94174@gmail.com',
                'telephone'  => '94174000',
                'country'    => 'TOGO',
                'city'       => 'Lomé',
                'password'   => \Illuminate\Support\Facades\Hash::make('password'),
                'role'       => 'client',
                'email_verified_at' => now(),
            ]);
            $this->command->info("   → Compte créé (mot de passe : password)");
        }

        $this->command->info("✅ Client trouvé : {$client->first_name} {$client->last_name} (#{$client->id})");

        // ── 2. Trouver un bien publié avec un agent assigné ──────────────────
        $bien = Bien::where('statut', 'publie')
            ->whereNotNull('agent_id')
            ->where('user_id', '!=', $client->id)  // pas son propre bien
            ->with(['agent', 'proprietaire'])
            ->first();

        if (! $bien) {
            // Fallback : n'importe quel bien publié même sans agent
            $bien = Bien::where('statut', 'publie')
                ->where('user_id', '!=', $client->id)
                ->with(['proprietaire'])
                ->first();
        }

        if (! $bien) {
            $this->command->error('Aucun bien publié trouvé. Lancez BienSeeder d\'abord.');
            return;
        }

        $this->command->info("✅ Bien trouvé : « {$bien->titre} » (#{$bien->id})");

        // Si pas d'agent, utiliser le premier agent disponible
        $agent = $bien->agent ?? User::where('role', 'agent')->first();

        if (! $agent) {
            $this->command->error('Aucun agent disponible. Créez un agent dans la BDD.');
            return;
        }

        if (! $bien->agent_id) {
            $bien->update(['agent_id' => $agent->id]);
            $this->command->line("   → Bien assigné à l'agent {$agent->first_name} {$agent->last_name}");
        }

        $this->command->info("✅ Agent : {$agent->first_name} {$agent->last_name} (#{$agent->id})");

        // ── 3. S'assurer que prix_visite est défini ──────────────────────────
        if (! $bien->prix_visite || (float) $bien->prix_visite <= 0) {
            $bien->update(['prix_visite' => 5000.00]);  // 5 000 FCFA par défaut
            $this->command->line('   → prix_visite fixé à 5 000 FCFA');
        }

        // ── 4. Créer ou récupérer la visite payée ────────────────────────────
        $visite = Visite::where('bien_id', $bien->id)
            ->where('client_id', $client->id)
            ->where('type_visite', Visite::TYPE_CLIENT)
            ->first();

        if ($visite) {
            $this->command->warn("⚠️  Visite existante trouvée (#{$visite->id}), mise à jour des créneaux.");
        } else {
            // Simuler un paiement confirmé
            $reference = 'VIS-SEED-' . strtoupper(Str::random(6));

            $paiement = Paiement::create([
                'type_paiement'         => 'visite',
                'payable_type'          => Bien::class,
                'payable_id'            => $bien->id,
                'montant'               => $bien->prix_visite,
                'operateur_paiement'    => 'TMONEY',
                'reference_transaction' => $reference,
                'statut'                => 'confirme',
            ]);

            Recu::create([
                'paiement_id'  => $paiement->id,
                'numero_recu'  => Recu::genererNumero(),
                'date_emission'=> now(),
            ]);

            $visite = Visite::create([
                'bien_id'    => $bien->id,
                'agent_id'   => $agent->id,
                'client_id'  => $client->id,
                'type_visite'=> Visite::TYPE_CLIENT,
                'statut'     => Visite::STATUT_PROPOSEE,
                'est_payee'  => true,
                'date_visite'=> now()->addDays(7),  // provisoire, sera remplacé au choix du créneau
                'notes'      => 'Visite créée par le seeder — paiement simulé.',
            ]);

            $this->command->info("✅ Visite créée (#{$visite->id}) — paiement simulé ({$reference})");
        }

        // ── 5. Créneaux par défaut proposés par l'agent ──────────────────────
        // 3 créneaux en semaine, heures de bureau, dans les 7 prochains jours
        // Modifiables facilement via l'interface agent ou directement en BDD.

        $base = Carbon::now()->addDays(2)->startOfDay();

        // Trouver 3 jours ouvrés à venir (pas samedi=6, pas dimanche=0)
        $creneaux = [];
        $tentative = $base->copy();
        while (count($creneaux) < 3) {
            if (! in_array($tentative->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                $creneaux[] = [
                    'date_debut'    => $tentative->copy()->setTime(9, 0)->toIso8601String(),
                    'duree_minutes' => 60,
                ];
                // 2e créneau le même jour à 14h
                if (count($creneaux) < 3) {
                    $creneaux[] = [
                        'date_debut'    => $tentative->copy()->setTime(14, 0)->toIso8601String(),
                        'duree_minutes' => 60,
                    ];
                }
            }
            $tentative->addDay();
        }

        // Limiter à 3 créneaux
        $creneaux = array_slice($creneaux, 0, 3);

        $visite->update([
            'creneaux_agent' => $creneaux,
            'statut'         => Visite::STATUT_EN_ATTENTE_CLIENT,
            'notes'          => "Créneaux proposés par l'agent {$agent->first_name} {$agent->last_name}. "
                . "Le client doit choisir l'un d'eux via l'application mobile.",
        ]);

        $this->command->info('');
        $this->command->info('✅ Créneaux par défaut proposés au client :');
        foreach ($creneaux as $i => $c) {
            $d = Carbon::parse($c['date_debut'])->locale('fr');
            $this->command->line("   [{$i}] " . $d->isoFormat('dddd D MMMM YYYY [à] HH[h]mm') . " ({$c['duree_minutes']} min)");
        }

        $this->command->info('');
        $this->command->info('📋 Résumé :');
        $this->command->table(
            ['Champ', 'Valeur'],
            [
                ['Client',         "{$client->first_name} {$client->last_name} ({$client->email})"],
                ['Bien',           "« {$bien->titre} »"],
                ['Agent',          "{$agent->first_name} {$agent->last_name}"],
                ['Visite ID',      $visite->id],
                ['Statut',         $visite->statut],
                ['Nb créneaux',    count($creneaux)],
                ['Action client',  'Choisir un créneau via GET /api/client/visites puis POST /api/client/visites/{id}/choisir-creneau'],
            ]
        );
    }
}
