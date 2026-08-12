<?php

namespace Database\Seeders;

use App\Models\Bien;
use App\Models\MediaBien;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Génère des biens de test pour le développement.
 * Lancer : php artisan db:seed --class=BienSeeder
 */
class BienSeeder extends Seeder
{
    public function run(): void
    {
        // Prend le premier client existant ou en crée un
        $client = User::where('role', 'client')->first();

        if (! $client) {
            $this->command->warn('Aucun client trouvé. Lancez d\'abord UserSeeder.');
            return;
        }

        $biens = [
            [
                'type_bien'        => 'appartement',
                'type_transaction' => 'vente',
                'titre'            => 'Bel appartement F4 à Tokoin',
                'description'      => 'Lumineux appartement de 4 pièces avec vue dégagée.',
                'prix'             => 45000000,
                'surface'          => 95,
                'nb_pieces'        => 4,
                'nb_salles_bain'   => 2,
                'adresse'          => 'Tokoin Wuiti, Lomé, Togo',
                'latitude'         => 6.1600,
                'longitude'        => 1.2210,
                'statut'           => 'publie',
            ],
            [
                'type_bien'        => 'villa',
                'type_transaction' => 'vente',
                'titre'            => 'Villa de standing à Bè Klikamé',
                'description'      => 'Villa 6 pièces avec piscine et jardin aménagé.',
                'prix'             => 120000000,
                'surface'          => 350,
                'nb_pieces'        => 6,
                'nb_salles_bain'   => 4,
                'adresse'          => 'Bè Klikamé, Lomé, Togo',
                'latitude'         => 6.1300,
                'longitude'        => 1.2350,
                'statut'           => 'publie',
            ],
            [
                'type_bien'        => 'terrain',
                'type_transaction' => 'vente',
                'titre'            => 'Terrain viabilisé 600m² à Agoè',
                'description'      => null,
                'prix'             => 8000000,
                'surface'          => 600,
                'nb_pieces'        => null,
                'nb_salles_bain'   => null,
                'adresse'          => 'Agoè Nyivé, Lomé, Togo',
                'latitude'         => 6.2050,
                'longitude'        => 1.2130,
                'statut'           => 'en_attente',
            ],
            [
                'type_bien'        => 'appartement',
                'type_transaction' => 'location',
                'titre'            => 'Studio meublé à Adidogomé',
                'description'      => 'Studio moderne entièrement meublé, idéal pour jeune professionnel.',
                'prix'             => 80000,
                'surface'          => 35,
                'nb_pieces'        => 1,
                'nb_salles_bain'   => 1,
                'adresse'          => 'Adidogomé, Lomé, Togo',
                'latitude'         => 6.1750,
                'longitude'        => 1.1880,
                'statut'           => 'publie',
            ],
        ];

        foreach ($biens as $data) {
            $bien = Bien::create(array_merge($data, [
                'user_id'   => $client->id,
                'publie_le' => $data['statut'] === 'publie' ? now() : null,
            ]));

            // Créer un média placeholder (pas de vrai fichier en seed)
            MediaBien::create([
                'bien_id'        => $bien->id,
                'type'           => 'photo',
                'chemin'         => "biens/{$bien->id}/medias/placeholder.jpg",
                'url'            => 'https://via.placeholder.com/800x600',
                'est_principale' => true,
                'ordre'          => 0,
                'mime_type'      => 'image/jpeg',
            ]);
        }

        $this->command->info('✅ ' . count($biens) . ' biens de test créés.');
    }
}
