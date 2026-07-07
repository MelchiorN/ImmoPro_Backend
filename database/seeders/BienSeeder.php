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
                'titre'            => 'Bel appartement F4 à Cocody',
                'description'      => 'Lumineux appartement de 4 pièces avec vue dégagée.',
                'prix'             => 45000000,
                'surface'          => 95,
                'nb_pieces'        => 4,
                'nb_salles_bain'   => 2,
                'adresse'          => 'Cocody Riviera 3, Abidjan, Côte d\'Ivoire',
                'latitude'         => 5.3790,
                'longitude'        => -3.9772,
                'statut'           => 'publie',
            ],
            [
                'type_bien'        => 'villa',
                'type_transaction' => 'vente',
                'titre'            => 'Villa de standing au Plateau',
                'description'      => 'Villa 6 pièces avec piscine et jardin aménagé.',
                'prix'             => 450000000,
                'surface'          => 450,
                'nb_pieces'        => 6,
                'nb_salles_bain'   => 4,
                'adresse'          => 'Plateau, Abidjan, Côte d\'Ivoire',
                'latitude'         => 5.3205,
                'longitude'        => -4.0167,
                'statut'           => 'publie',
            ],
            [
                'type_bien'        => 'terrain',
                'type_transaction' => 'vente',
                'titre'            => 'Terrain viabilisé 600m² à Yopougon',
                'description'      => null,
                'prix'             => 12000000,
                'surface'          => 600,
                'nb_pieces'        => null,
                'nb_salles_bain'   => null,
                'adresse'          => 'Yopougon Selmer, Abidjan, Côte d\'Ivoire',
                'latitude'         => 5.3706,
                'longitude'        => -4.0708,
                'statut'           => 'en_attente',
            ],
            [
                'type_bien'        => 'appartement',
                'type_transaction' => 'location',
                'titre'            => 'Studio meublé à Marcory',
                'description'      => 'Studio moderne entièrement meublé, idéal pour jeune professionnel.',
                'prix'             => 150000,
                'surface'          => 35,
                'nb_pieces'        => 1,
                'nb_salles_bain'   => 1,
                'adresse'          => 'Marcory Zone 4, Abidjan, Côte d\'Ivoire',
                'latitude'         => 5.3009,
                'longitude'        => -3.9869,
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
