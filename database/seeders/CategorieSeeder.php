<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorieSeeder extends Seeder
{
    public function run(): void
    {
        // Nettoyer dans l'ordre (FK oblige)
        DB::table('attribut_definitions')->delete();
        DB::table('categories')->delete();

        foreach ($this->categoriesData() as $ordre => $categorie) {
            $categorieId = (string) Str::uuid();

            DB::table('categories')->insert([
                'id'               => $categorieId,
                'nom'              => $categorie['nom'],
                'slug'             => $categorie['slug'],
                'description'      => $categorie['description'],
                'actif'            => true,
                'ordre_affichage'  => $ordre + 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($categorie['attributs'] as $ordreChamp => $attribut) {
                DB::table('attribut_definitions')->insert([
                    'id'               => (string) Str::uuid(),
                    'categorie_id'     => $categorieId,
                    'nom_champ'        => $attribut['nom_champ'],
                    'label_affiche'    => $attribut['label_affiche'],
                    'type_champ'       => $attribut['type_champ'],
                    'options_enum'     => isset($attribut['options_enum'])
                                            ? json_encode($attribut['options_enum'])
                                            : null,
                    'obligatoire'      => $attribut['obligatoire'] ?? false,
                    'est_socle'        => $attribut['est_socle'] ?? false,
                    'actif'            => true,
                    'ordre_affichage'  => $ordreChamp + 1,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }

        $this->command->info('✅ Catégories et attributs insérés avec succès.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Données de référence — tirées de pp.md
    // ─────────────────────────────────────────────────────────────────────────

    private function categoriesData(): array
    {
        return [

            // ── 1. Maison / Villa ─────────────────────────────────────────────
            [
                'nom'         => 'Maison / Villa',
                'slug'        => 'maison',
                'description' => 'Bien immobilier individuel bâti sur une parcelle, loué ou vendu dans son intégralité.',
                'attributs'   => [
                    // ─ Caractéristiques ─
                    ['nom_champ' => 'superficie_terrain',  'label_affiche' => 'Superficie du terrain (m²)',  'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_chambres',          'label_affiche' => 'Nombre de chambres',           'type_champ' => 'nombre', 'obligatoire' => true,  'est_socle' => true],
                    ['nom_champ' => 'nb_salons',            'label_affiche' => 'Nombre de salons',             'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => true],
                    ['nom_champ' => 'nb_sdb',               'label_affiche' => 'Nombre de salles de bain',    'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => true],
                    ['nom_champ' => 'nb_toilettes',         'label_affiche' => 'Nombre de toilettes',          'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_cuisines',          'label_affiche' => 'Nombre de cuisines',           'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_balcons',           'label_affiche' => 'Nombre de balcons',            'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_terrasses',         'label_affiche' => 'Nombre de terrasses',          'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_etages',            'label_affiche' => "Nombre d'étages",              'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    [
                        'nom_champ'     => 'etat_bien',
                        'label_affiche' => 'État du bien',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['neuf', 'bon_etat', 'a_renover'],
                        'obligatoire'   => true,
                        'est_socle'     => true,
                    ],
                    // ─ Garage ─
                    ['nom_champ' => 'garage_disponible',   'label_affiche' => 'Garage disponible',            'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'capacite_garage',     'label_affiche' => 'Capacité du garage (nb véhicules)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    // ─ Équipements ─
                    ['nom_champ' => 'eau_courante',        'label_affiche' => 'Eau courante',                 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'electricite',         'label_affiche' => 'Électricité',                  'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'internet_fibre',      'label_affiche' => 'Internet / Fibre',             'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'cameras',             'label_affiche' => 'Caméras / Sécurité',           'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'meuble',              'label_affiche' => 'Meublé',                       'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'jardin',              'label_affiche' => 'Jardin',                       'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'piscine',             'label_affiche' => 'Piscine',                      'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                ],
            ],

            // ── 2. Appartement ───────────────────────────────────────────────
            [
                'nom'         => 'Appartement',
                'slug'        => 'appartement',
                'description' => 'Unité de logement complète et autonome au sein d\'un immeuble, louée ou vendue dans son intégralité.',
                'attributs'   => [
                    ['nom_champ' => 'etage_appartement',   'label_affiche' => "Étage de l'appartement",       'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_etages_immeuble',  'label_affiche' => "Nombre total d'étages (immeuble)", 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_chambres',          'label_affiche' => 'Nombre de chambres',           'type_champ' => 'nombre',  'obligatoire' => true,  'est_socle' => true],
                    ['nom_champ' => 'nb_salons',            'label_affiche' => 'Nombre de salons',             'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => true],
                    ['nom_champ' => 'nb_sdb',               'label_affiche' => 'Nombre de salles de bain',    'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => true],
                    ['nom_champ' => 'nb_toilettes',         'label_affiche' => 'Nombre de toilettes',          'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_cuisines',          'label_affiche' => 'Nombre de cuisines',           'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_balcons',           'label_affiche' => 'Nombre de balcons',            'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'ascenseur',            'label_affiche' => 'Ascenseur disponible',         'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    [
                        'nom_champ'     => 'etat_bien',
                        'label_affiche' => 'État du bien',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['neuf', 'bon_etat', 'a_renover'],
                        'obligatoire'   => true,
                        'est_socle'     => true,
                    ],
                    // ─ Parking ─
                    ['nom_champ' => 'parking_disponible',  'label_affiche' => 'Parking disponible',           'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    [
                        'nom_champ'     => 'type_parking',
                        'label_affiche' => 'Type de parking',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['prive', 'partage'],
                        'obligatoire'   => false,
                        'est_socle'     => false,
                    ],
                    ['nom_champ' => 'capacite_parking',    'label_affiche' => 'Capacité parking (nb véhicules)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    // ─ Équipements ─
                    ['nom_champ' => 'eau_courante',        'label_affiche' => 'Eau courante',                 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'electricite',         'label_affiche' => 'Électricité',                  'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'internet_fibre',      'label_affiche' => 'Internet / Fibre',             'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'cameras',             'label_affiche' => 'Caméras / Sécurité (gardien)', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'meuble',              'label_affiche' => 'Meublé',                       'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'climatisation',       'label_affiche' => 'Climatisation',                'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                ],
            ],

            // ── 3. Villa (alias de Maison — même schéma, slug distinct pour l'enum) ──
            [
                'nom'         => 'Villa',
                'slug'        => 'villa',
                'description' => 'Villa individuelle bâtie sur une parcelle, louée ou vendue dans son intégralité. Même logique que Maison.',
                'attributs'   => [
                    ['nom_champ' => 'superficie_terrain',  'label_affiche' => 'Superficie du terrain (m²)',  'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_chambres',          'label_affiche' => 'Nombre de chambres',           'type_champ' => 'nombre',  'obligatoire' => true,  'est_socle' => true],
                    ['nom_champ' => 'nb_salons',            'label_affiche' => 'Nombre de salons',             'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => true],
                    ['nom_champ' => 'nb_sdb',               'label_affiche' => 'Nombre de salles de bain',    'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => true],
                    ['nom_champ' => 'nb_toilettes',         'label_affiche' => 'Nombre de toilettes',          'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_cuisines',          'label_affiche' => 'Nombre de cuisines',           'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_balcons',           'label_affiche' => 'Nombre de balcons',            'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_terrasses',         'label_affiche' => 'Nombre de terrasses',          'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_etages',            'label_affiche' => "Nombre d'étages",              'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'etat_bien',            'label_affiche' => 'État du bien',                 'type_champ' => 'enum',    'options_enum' => ['neuf', 'bon_etat', 'a_renover'], 'obligatoire' => true, 'est_socle' => true],
                    ['nom_champ' => 'garage_disponible',   'label_affiche' => 'Garage disponible',            'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'capacite_garage',     'label_affiche' => 'Capacité du garage (nb véhicules)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'eau_courante',        'label_affiche' => 'Eau courante',                 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'electricite',         'label_affiche' => 'Électricité',                  'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'internet_fibre',      'label_affiche' => 'Internet / Fibre',             'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'cameras',             'label_affiche' => 'Caméras / Sécurité',           'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'meuble',              'label_affiche' => 'Meublé',                       'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'jardin',              'label_affiche' => 'Jardin',                       'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'piscine',             'label_affiche' => 'Piscine',                      'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                ],
            ],

            // ── 4. Terrain / Parcelle ────────────────────────────────────────
            [
                'nom'         => 'Terrain / Parcelle',
                'slug'        => 'terrain',
                'description' => 'Bien immobilier non bâti, vendu ou destiné à la construction, sans structure d\'habitation existante.',
                'attributs'   => [
                    [
                        'nom_champ'     => 'type_terrain',
                        'label_affiche' => 'Type de terrain',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['titre', 'non_titre', 'en_cours_titrisation'],
                        'obligatoire'   => true,
                        'est_socle'     => true,
                    ],
                    [
                        'nom_champ'     => 'usage_prevu',
                        'label_affiche' => 'Usage prévu',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['residentiel', 'commercial', 'agricole', 'mixte'],
                        'obligatoire'   => false,
                        'est_socle'     => false,
                    ],
                    ['nom_champ' => 'terrain_cloture',     'label_affiche' => 'Terrain clôturé',              'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    // ─ Viabilisation ─
                    ['nom_champ' => 'acces_eau',           'label_affiche' => "Accès à l'eau",                'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'acces_electricite',   'label_affiche' => "Accès à l'électricité",       'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'route_bitumee',       'label_affiche' => 'Route bitumée à proximité',   'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                ],
            ],

            // ── 5. Bureau / Local commercial ─────────────────────────────────
            [
                'nom'         => 'Bureau / Local commercial',
                'slug'        => 'bureau_commerce',
                'description' => 'Espace à usage professionnel (bureau, boutique, local commercial), loué ou vendu dans son intégralité.',
                'attributs'   => [
                    ['nom_champ' => 'nb_pieces_bureaux',   'label_affiche' => 'Nombre de pièces / bureaux',  'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => true],
                    ['nom_champ' => 'nb_salles_reunion',   'label_affiche' => 'Nombre de salles de réunion', 'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_toilettes',         'label_affiche' => 'Nombre de toilettes',          'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    [
                        'nom_champ'     => 'niveau',
                        'label_affiche' => 'Niveau',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['rez_de_chaussee', 'etage'],
                        'obligatoire'   => false,
                        'est_socle'     => false,
                    ],
                    ['nom_champ' => 'vitrine_rue',         'label_affiche' => 'Vitrine sur rue',              'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    [
                        'nom_champ'     => 'etat_bien',
                        'label_affiche' => 'État du bien',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['neuf', 'bon_etat', 'a_renover'],
                        'obligatoire'   => true,
                        'est_socle'     => true,
                    ],
                    // ─ Parking ─
                    ['nom_champ' => 'parking_disponible',  'label_affiche' => 'Parking disponible',           'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'capacite_parking',    'label_affiche' => 'Capacité parking (nb véhicules)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    // ─ Équipements ─
                    ['nom_champ' => 'eau_courante',        'label_affiche' => 'Eau courante',                 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'electricite',         'label_affiche' => 'Électricité',                  'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'internet_fibre',      'label_affiche' => 'Internet / Fibre',             'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'climatisation',       'label_affiche' => 'Climatisation',                'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'cameras',             'label_affiche' => 'Caméras / Sécurité',           'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                ],
            ],

            // ── 6. Chambre / Studio ──────────────────────────────────────────
            [
                'nom'         => 'Chambre / Studio',
                'slug'        => 'chambre_studio',
                'description' => 'Unité individuelle de taille réduite, destinée à être louée à une seule personne ou un seul foyer restreint.',
                'attributs'   => [
                    [
                        'nom_champ'     => 'type_unite',
                        'label_affiche' => 'Type',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['chambre_simple', 'chambre_salon', 'studio'],
                        'obligatoire'   => true,
                        'est_socle'     => true,
                    ],
                    ['nom_champ' => 'sdb_privee',           'label_affiche' => 'Salle de bain privée',         'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => true],
                    ['nom_champ' => 'cuisine_privee',       'label_affiche' => 'Cuisine privée',               'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'meuble',               'label_affiche' => 'Meublé',                       'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    [
                        'nom_champ'     => 'etat_bien',
                        'label_affiche' => 'État du bien',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['neuf', 'bon_etat', 'a_renover'],
                        'obligatoire'   => true,
                        'est_socle'     => true,
                    ],
                    // ─ Parking ─
                    ['nom_champ' => 'parking_disponible',   'label_affiche' => 'Parking disponible',           'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'parking_partage',      'label_affiche' => 'Parking partagé avec autres locataires', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'capacite_parking',     'label_affiche' => 'Capacité parking totale',      'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    // ─ Équipements ─
                    ['nom_champ' => 'eau_courante',         'label_affiche' => 'Eau courante',                 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'electricite',          'label_affiche' => 'Électricité',                  'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'internet_fibre',       'label_affiche' => 'Internet / Fibre',             'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'cameras',              'label_affiche' => 'Caméras / Sécurité',           'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                ],
            ],

        ];
    }
}
