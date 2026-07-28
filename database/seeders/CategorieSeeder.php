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
                'id'                      => $categorieId,
                'nom'                     => $categorie['nom'],
                'slug'                    => $categorie['slug'],
                'description'             => $categorie['description'],
                'actif'                   => true,
                'ordre_affichage'         => $ordre + 1,
                'pourcentage_commission'  => $categorie['pourcentage_commission'] ?? 0,
                'frais_etude_pourcentage' => $categorie['frais_etude_pourcentage'] ?? 0,
                'created_at'              => now(),
                'updated_at'              => now(),
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

    private function categoriesData(): array
    {
        // Attributs communs pour Maison / Villa
        $attributesMaisonVilla = [
            // Chambres et sanitaires (Option 1)
            ['nom_champ' => 'nb_chambres', 'label_affiche' => 'Nombre total de chambres', 'type_champ' => 'nombre', 'obligatoire' => true, 'est_socle' => true],
            ['nom_champ' => 'nb_chambres_sdb_privative', 'label_affiche' => 'Nombre de chambres avec salle d\'eau privative (Douche + WC)', 'type_champ' => 'nombre', 'obligatoire' => true, 'est_socle' => false],
            ['nom_champ' => 'nb_chambres_douche_privative', 'label_affiche' => 'Nombre de chambres avec douche privative uniquement', 'type_champ' => 'nombre', 'obligatoire' => true, 'est_socle' => false],
            ['nom_champ' => 'nb_chambres_wc_privatif', 'label_affiche' => 'Nombre de chambres avec WC privatif uniquement', 'type_champ' => 'nombre', 'obligatoire' => true, 'est_socle' => false],
            
            // Sanitaires communs
            ['nom_champ' => 'nb_wc_communs', 'label_affiche' => 'Nombre de toilettes/WC communs (sans douche)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'nb_douches_communes', 'label_affiche' => 'Nombre de salles de douche communes (sans WC)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'nb_salle_eau_complete_commune', 'label_affiche' => 'Nombre de salles d\'eau complètes (Douche + WC) communes', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            
            // Chambres annexes
            ['nom_champ' => 'chambre_annexe_existe', 'label_affiche' => 'Chambre(s) annexe(s)', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'chambre_annexe_nb', 'label_affiche' => 'Nombre de chambres annexes', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'chambre_annexe_wc_interne', 'label_affiche' => 'WC/Toilette interne pour annexe ?', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            [
                'nom_champ' => 'chambre_annexe_role_occupant',
                'label_affiche' => 'Rôle occupant',
                'type_champ' => 'enum',
                'options_enum' => ['chauffeur', 'gardien', 'aide_menagere', 'autre'],
                'obligatoire' => false,
                'est_socle' => false
            ],
            // Pièces de vie
            ['nom_champ' => 'nb_salons', 'label_affiche' => 'Nombre de salons', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => true],
            [
                'nom_champ' => 'cuisine_configuration',
                'label_affiche' => 'Cuisine',
                'type_champ' => 'enum',
                'options_enum' => ['interne_non_equipee', 'interne_equipee', 'externe_non_equipee', 'externe_equipee'],
                'obligatoire' => false,
                'est_socle' => false
            ],
            ['nom_champ' => 'terrasse_balcon', 'label_affiche' => 'Terrasse/Balcon', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'jardin', 'label_affiche' => 'Jardin', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'piscine', 'label_affiche' => 'Piscine', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            // Niveau & Superficie
            ['nom_champ' => 'niveau', 'label_affiche' => 'Niveau / Étage', 'type_champ' => 'texte', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'superficie_batie', 'label_affiche' => 'Superficie bâtie (m²)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            // Électricité
            [
                'nom_champ' => 'compteur_electricite_type',
                'label_affiche' => 'Compteur d\'électricité (bien)',
                'type_champ' => 'enum',
                'options_enum' => ['sous_compteur_mecanique', 'sous_compteur_cashpower', 'additionneuse_mecanique', 'additionneuse_cashpower', 'compteur_principal_mecanique', 'compteur_principal_cashpower', 'aucun'],
                'obligatoire' => false,
                'est_socle' => false
            ],
            ['nom_champ' => 'groupe_electrogene_onduleur', 'label_affiche' => 'Groupe électrogène / Onduleur', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            // Eau courante
            [
                'nom_champ' => 'compteur_eau_type',
                'label_affiche' => 'Compteur d\'eau (bien)',
                'type_champ' => 'enum',
                'options_enum' => ['sous_compteur_mecanique', 'sous_compteur_cashpower', 'additionneuse_mecanique', 'additionneuse_cashpower', 'compteur_principal_mecanique', 'compteur_principal_cashpower', 'aucun'],
                'obligatoire' => false,
                'est_socle' => false
            ],
            [
                'nom_champ' => 'source_eau',
                'label_affiche' => 'Source d\'accès à l\'eau',
                'type_champ' => 'enum',
                'options_enum' => ['robinet_ville', 'forage_prive', 'chateau_eau'],
                'obligatoire' => false,
                'est_socle' => false
            ],
            // Confort & Connectivité
            ['nom_champ' => 'meuble', 'label_affiche' => 'Meublé', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'climatisation', 'label_affiche' => 'Climatisation', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'internet_fibre', 'label_affiche' => 'Internet / Fibre', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            // Stationnement
            ['nom_champ' => 'garage_existe', 'label_affiche' => 'Garage disponible', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            [
                'nom_champ' => 'garage_type_vehicule',
                'label_affiche' => 'Type de véhicules admis',
                'type_champ' => 'enum',
                'options_enum' => ['deux_roues', 'quatre_roues', 'les_deux'],
                'obligatoire' => false,
                'est_socle' => false
            ],
            ['nom_champ' => 'garage_capacite', 'label_affiche' => 'Capacité du garage (nb véhicules)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            // Sécurité
            ['nom_champ' => 'securite_gardiennage', 'label_affiche' => 'Sécurité — Gardiennage', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'securite_videosurveillance', 'label_affiche' => 'Sécurité — Vidéosurveillance', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'securite_alarme', 'label_affiche' => 'Sécurité — Alarme', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            // Autres
            [
                'nom_champ' => 'etat_bien',
                'label_affiche' => 'État du bien',
                'type_champ' => 'enum',
                'options_enum' => ['neuf', 'bon_etat', 'a_renover'],
                'obligatoire' => true,
                'est_socle' => true
            ]
        ];

        // Sanitaires communs et compteurs pour Bureau, Commerce, Entrepôt
        $sharedSanitairesAndMeters = [
            ['nom_champ' => 'toilette_existe', 'label_affiche' => 'Toilette / WC existe', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'toilette_emplacement', 'label_affiche' => 'Situation de la toilette / WC', 'type_champ' => 'enum', 'options_enum' => ['interne', 'externe'], 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'toilette_usage', 'label_affiche' => 'Type d\'accès toilette / WC', 'type_champ' => 'enum', 'options_enum' => ['privee', 'partagee'], 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'nb_toilettes', 'label_affiche' => 'Nombre de toilettes / WC', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            [
                'nom_champ' => 'compteur_electricite_type',
                'label_affiche' => 'Compteur d\'électricité (bien)',
                'type_champ' => 'enum',
                'options_enum' => ['sous_compteur_mecanique', 'sous_compteur_cashpower', 'additionneuse_mecanique', 'additionneuse_cashpower', 'compteur_principal_mecanique', 'compteur_principal_cashpower', 'aucun'],
                'obligatoire' => false,
                'est_socle' => false
            ],
            [
                'nom_champ' => 'compteur_eau_type',
                'label_affiche' => 'Compteur d\'eau (bien)',
                'type_champ' => 'enum',
                'options_enum' => ['sous_compteur_mecanique', 'sous_compteur_cashpower', 'additionneuse_mecanique', 'additionneuse_cashpower', 'compteur_principal_mecanique', 'compteur_principal_cashpower', 'aucun'],
                'obligatoire' => false,
                'est_socle' => false
            ],
            ['nom_champ' => 'niveau', 'label_affiche' => 'Niveau / Étage', 'type_champ' => 'texte', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'parking_visiteur_places', 'label_affiche' => 'Parking visiteur (nb places)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'parking_personnel_places', 'label_affiche' => 'Parking personnel (nb places)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            [
                'nom_champ' => 'parking_type_vehicule',
                'label_affiche' => 'Type de véhicule (parking)',
                'type_champ' => 'enum',
                'options_enum' => ['deux_roues', 'quatre_roues', 'les_deux'],
                'obligatoire' => false,
                'est_socle' => false
            ],
            ['nom_champ' => 'climatisation', 'label_affiche' => 'Climatisation', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'internet_fibre', 'label_affiche' => 'Internet / Fibre', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'securite_gardiennage', 'label_affiche' => 'Sécurité — Gardiennage', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'securite_videosurveillance', 'label_affiche' => 'Sécurité — Vidéosurveillance', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'securite_alarme', 'label_affiche' => 'Sécurité — Alarme', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'etat_bien', 'label_affiche' => 'État du bien', 'type_champ' => 'enum', 'options_enum' => ['neuf', 'bon_etat', 'a_renover'], 'obligatoire' => true, 'est_socle' => true]
        ];

        return [
            // ── 1. Maison ─────────────────────────────────────────────────────
            [
                'nom'                     => 'Maison',
                'slug'                    => 'maison',
                'description'             => 'Bien immobilier individuel bâti, loué ou vendu dans son intégralité.',
                'pourcentage_commission'  => 5.00,
                'frais_etude_pourcentage' => 1.50,
                'attributs'               => $attributesMaisonVilla,
            ],

            // ── 2. Villa ──────────────────────────────────────────────────────
            [
                'nom'                     => 'Villa',
                'slug'                    => 'villa',
                'description'             => 'Villa individuelle bâtie avec jardin, louée ou vendue dans son intégralité.',
                'pourcentage_commission'  => 5.00,
                'frais_etude_pourcentage' => 2.00,
                'attributs'               => $attributesMaisonVilla,
            ],

            // ── 3. Appartement ────────────────────────────────────────────────
            [
                'nom'                     => 'Appartement',
                'slug'                    => 'appartement',
                'description'             => 'Logement autonome au sein d\'un immeuble, incluant Studios et appartements F2, F3, F4+.',
                'pourcentage_commission'  => 7.50,
                'frais_etude_pourcentage' => 1.00,
                'attributs'               => [
                    // Type et structure
                    [
                        'nom_champ'     => 'type_appartement',
                        'label_affiche' => 'Type de logement',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['studio_f1', 'f2', 'f3', 'f4_plus'],
                        'obligatoire'   => true,
                        'est_socle'     => true
                    ],
                    ['nom_champ' => 'nb_chambres', 'label_affiche' => 'Nombre total de chambres', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => true],
                    ['nom_champ' => 'nb_chambres_sdb_privative', 'label_affiche' => 'Nombre de chambres avec salle d\'eau privative (Douche + WC)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_chambres_douche_privative', 'label_affiche' => 'Nombre de chambres avec douche privative uniquement', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_chambres_wc_privatif', 'label_affiche' => 'Nombre de chambres avec WC privatif uniquement', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    
                    // Sanitaires communs
                    ['nom_champ' => 'nb_wc_communs', 'label_affiche' => 'Nombre de toilettes/WC communs (sans douche)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_douches_communes', 'label_affiche' => 'Nombre de salles de douche communes (sans WC)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_salle_eau_complete_commune', 'label_affiche' => 'Nombre de salles d\'eau complètes (Douche + WC) communes', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    
                    // Toilettes (spécifique Studio/F1)
                    ['nom_champ' => 'toilette_existe', 'label_affiche' => 'Toilette / WC existe', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    [
                        'nom_champ'     => 'toilette_emplacement',
                        'label_affiche' => 'Situation de la toilette / WC',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['interne', 'externe'],
                        'obligatoire'   => false,
                        'est_socle'     => false
                    ],
                    [
                        'nom_champ'     => 'toilette_usage',
                        'label_affiche' => 'Type d\'accès toilette / WC',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['privee', 'partagee'],
                        'obligatoire'   => false,
                        'est_socle'     => false
                    ],
                    ['nom_champ' => 'nb_toilettes', 'label_affiche' => 'Nombre de toilettes / WC (Studio)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    // Localisation
                    [
                        'nom_champ'     => 'situation_immeuble',
                        'label_affiche' => 'Situation dans l\'immeuble',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['rez_de_chaussee', 'a_l_etage'],
                        'obligatoire'   => false,
                        'est_socle'     => false
                    ],
                    ['nom_champ' => 'etage_occupe', 'label_affiche' => 'Étage occupé', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_etages_immeuble', 'label_affiche' => 'Nombre total d\'étages de l\'immeuble', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    // Structure
                    ['nom_champ' => 'cuisine', 'label_affiche' => 'Cuisine existe', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'terrasse', 'label_affiche' => 'Terrasse/Balcon', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'magasin_debarras', 'label_affiche' => 'Magasin / Débarras', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'superficie_habitable', 'label_affiche' => 'Superficie habitable (m²)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    // Électricité
                    [
                        'nom_champ'     => 'compteur_electricite_type',
                        'label_affiche' => 'Compteur d\'électricité (bien)',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['sous_compteur_mecanique', 'sous_compteur_cashpower', 'additionneuse_mecanique', 'additionneuse_cashpower', 'compteur_principal_mecanique', 'compteur_principal_cashpower', 'aucun'],
                        'obligatoire'   => false,
                        'est_socle'     => false
                    ],
                    // Eau courante
                    [
                        'nom_champ'     => 'compteur_eau_type',
                        'label_affiche' => 'Compteur d\'eau (bien)',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['sous_compteur_mecanique', 'sous_compteur_cashpower', 'additionneuse_mecanique', 'additionneuse_cashpower', 'compteur_principal_mecanique', 'compteur_principal_cashpower', 'aucun'],
                        'obligatoire'   => false,
                        'est_socle'     => false
                    ],
                    // Confort & Parking
                    ['nom_champ' => 'meuble', 'label_affiche' => 'Meublé', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'carrele', 'label_affiche' => 'Carrelé', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'climatisation', 'label_affiche' => 'Climatisation', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'internet_fibre', 'label_affiche' => 'Internet / Fibre', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'parking_existe', 'label_affiche' => 'Parking disponible', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    [
                        'nom_champ'     => 'parking_type_vehicule',
                        'label_affiche' => 'Type de véhicule (parking)',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['deux_roues', 'quatre_roues', 'les_deux'],
                        'obligatoire'   => false,
                        'est_socle'     => false
                    ],
                    ['nom_champ' => 'parking_capacite', 'label_affiche' => 'Capacité du parking (nb places)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    [
                        'nom_champ'     => 'etat_bien',
                        'label_affiche' => 'État du bien',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['neuf', 'bon_etat', 'a_renover'],
                        'obligatoire'   => true,
                        'est_socle'     => true
                    ]
                ],
            ],

            // ── 4. Bureau ─────────────────────────────────────────────────────
            [
                'nom'                     => 'Bureau',
                'slug'                    => 'bureau',
                'description'             => 'Espace de travail professionnel cloisonné ou open space.',
                'pourcentage_commission'  => 8.00,
                'frais_etude_pourcentage' => 3.00,
                'attributs'               => array_merge([
                    ['nom_champ' => 'bureau_configuration', 'label_affiche' => 'Configuration', 'type_champ' => 'enum', 'options_enum' => ['open_space', 'cloisonne', 'mixte'], 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'bureau_nb_pieces', 'label_affiche' => 'Nombre de pièces / bureaux', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => true],
                    ['nom_champ' => 'bureau_salle_reunion', 'label_affiche' => 'Salle de réunion', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'bureau_capacite_postes', 'label_affiche' => 'Capacité (postes de travail)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                ], $sharedSanitairesAndMeters),
            ],

            // ── 5. Local Commercial ───────────────────────────────────────────
            [
                'nom'                     => 'Local commercial',
                'slug'                    => 'commerce',
                'description'             => 'Espace commercial, boutique ou pas-de-porte pour activités de vente.',
                'pourcentage_commission'  => 10.00,
                'frais_etude_pourcentage' => 3.50,
                'attributs'               => array_merge([
                    ['nom_champ' => 'commerce_vitrine', 'label_affiche' => 'Vitrine sur rue', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'commerce_enseigne_autorisee', 'label_affiche' => 'Enseigne extérieure autorisée', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'commerce_reserve', 'label_affiche' => 'Arrière-boutique / Réserve', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'commerce_emplacement', 'label_affiche' => 'Emplacement', 'type_champ' => 'enum', 'options_enum' => ['avenue_principale', 'rue_secondaire', 'marche', 'autre'], 'obligatoire' => false, 'est_socle' => false],
                ], $sharedSanitairesAndMeters),
            ],

            // ── 6. Entrepôt ───────────────────────────────────────────────────
            [
                'nom'                     => 'Entrepôt',
                'slug'                    => 'entrepot',
                'description'             => 'Bâtiment industriel ou de stockage pour activités logistiques.',
                'pourcentage_commission'  => 5.00,
                'frais_etude_pourcentage' => 2.50,
                'attributs'               => array_merge([
                    ['nom_champ' => 'entrepot_hauteur_plafond', 'label_affiche' => 'Hauteur sous plafond (m)', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'entrepot_quai_chargement', 'label_affiche' => 'Quai de chargement', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'entrepot_quai_nb', 'label_affiche' => 'Nombre de quais', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'entrepot_acces_poids_lourd', 'label_affiche' => 'Accès poids lourd', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'entrepot_zone_climatisee', 'label_affiche' => 'Zone climatisée / réfrigérée', 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                ], $sharedSanitairesAndMeters),
            ],

            // ── 7. Terrain nu ─────────────────────────────────────────────────
            [
                'nom'                     => 'Terrain / Parcelle',
                'slug'                    => 'terrain',
                'description'             => 'Bien immobilier non bâti, sans construction.',
                'pourcentage_commission'  => 5.00,
                'frais_etude_pourcentage' => 1.50,
                'attributs'               => [
                    [
                        'nom_champ'     => 'terrain_usage',
                        'label_affiche' => 'Type d\'usage autorisé',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['habitation', 'commercial', 'agricole'],
                        'obligatoire'   => true,
                        'est_socle'     => true
                    ],
                    [
                        'nom_champ'     => 'terrain_mode',
                        'label_affiche' => 'Mode d\'exploitation',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['bail', 'location'],
                        'obligatoire'   => true,
                        'est_socle'     => true
                    ],
                    ['nom_champ' => 'terrain_duree', 'label_affiche' => 'Durée de mise à disposition', 'type_champ' => 'nombre', 'obligatoire' => true, 'est_socle' => true],
                    [
                        'nom_champ'     => 'terrain_duree_unite',
                        'label_affiche' => 'Unité de la durée',
                        'type_champ'    => 'enum',
                        'options_enum'  => ['annees', 'mois'],
                        'obligatoire'   => true,
                        'est_socle'     => true
                    ]
                ],
            ],
        ];
    }
}
