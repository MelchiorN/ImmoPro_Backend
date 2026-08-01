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
        DB::table('types_logement')->delete();
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

            // Insérer les types de logement si définis (ex: appartement)
            if (!empty($categorie['types_logement'])) {
                foreach ($categorie['types_logement'] as $ordreType => $type) {
                    DB::table('types_logement')->insert([
                        'id'           => (string) Str::uuid(),
                        'categorie_id' => $categorieId,
                        'slug'         => $type['slug'],
                        'nom'          => $type['nom'],
                        'description'  => $type['description'] ?? null,
                        'est_socle'    => $type['est_socle'] ?? true,
                        'actif'        => true,
                        'ordre'        => $ordreType + 1,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }

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

        $this->command->info('✅ Catégories, types logement et attributs insérés avec succès.');
    }

    private function categoriesData(): array
    {
        // ── Compteurs (options partagées) ────────────────────────────────────
        $optsCompteur = [
            'sous_compteur_mecanique', 'sous_compteur_cashpower',
            'additionneuse_mecanique', 'additionneuse_cashpower',
            'compteur_principal_mecanique', 'compteur_principal_cashpower',
            'aucun',
        ];

        // ── Attributs communs Maison / Villa ─────────────────────────────────
        $attrsMaisonVilla = [
            // Chambres
            ['nom_champ' => 'nb_chambres', 'label_affiche' => 'Nombre total de chambres', 'type_champ' => 'nombre', 'obligatoire' => true,  'est_socle' => true],
            ['nom_champ' => 'nb_chambres_sdb_privative',    'label_affiche' => 'Chambres avec Douche + WC privatifs',    'type_champ' => 'nombre', 'obligatoire' => true,  'est_socle' => false],
            ['nom_champ' => 'nb_chambres_douche_privative', 'label_affiche' => 'Chambres avec douche privative seule',   'type_champ' => 'nombre', 'obligatoire' => true,  'est_socle' => false],
            ['nom_champ' => 'nb_chambres_wc_privatif',      'label_affiche' => 'Chambres avec WC privatif seul',         'type_champ' => 'nombre', 'obligatoire' => true,  'est_socle' => false],
            // Sanitaires communs
            ['nom_champ' => 'nb_wc_communs',                 'label_affiche' => 'Toilettes/WC communs (sans douche)',    'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'nb_douches_communes',            'label_affiche' => 'Salles de douche communes (sans WC)',   'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'nb_salle_eau_complete_commune',  'label_affiche' => 'Salles d\'eau complètes (Douche+WC) communes', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            // Annexes — condition frontend: chambre_annexe_existe = true
            ['nom_champ' => 'chambre_annexe_existe',          'label_affiche' => 'Chambre(s) annexe(s) ?',               'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'chambre_annexe_nb',              'label_affiche' => 'Nombre de chambres annexes',            'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'chambre_annexe_wc_interne',      'label_affiche' => 'Annexe : WC/Toilette interne',          'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'chambre_annexe_role_occupant',   'label_affiche' => 'Rôle de l\'occupant annexe',            'type_champ' => 'enum', 'options_enum' => ['chauffeur', 'gardien', 'aide_menagere', 'autre'], 'obligatoire' => false, 'est_socle' => false],
            // Pièces de vie
            ['nom_champ' => 'nb_salons',                      'label_affiche' => 'Nombre de salons',                     'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => true],
            ['nom_champ' => 'cuisine_configuration',          'label_affiche' => 'Cuisine',                              'type_champ' => 'enum', 'options_enum' => ['interne_non_equipee', 'interne_equipee', 'externe_non_equipee', 'externe_equipee'], 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'terrasse_balcon',                'label_affiche' => 'Terrasse / Balcon',                    'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'jardin',                         'label_affiche' => 'Jardin',                               'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'piscine',                        'label_affiche' => 'Piscine',                              'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            // Niveau & superficie
            // NOTE: "superficie_batie" = surface construite (maison/villa)
            // "superficie" socle (table biens) = superficie du terrain
            // Pas de "surface_habitable" ici : évite la redondance batie vs habitable
            ['nom_champ' => 'niveau',                         'label_affiche' => 'Niveau / Étage',                       'type_champ' => 'texte',  'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'superficie_batie',               'label_affiche' => 'Surface bâtie (m²)',                   'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            // Électricité & eau
            ['nom_champ' => 'compteur_electricite_type',     'label_affiche' => 'Compteur d\'électricité',              'type_champ' => 'enum', 'options_enum' => $optsCompteur, 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'groupe_electrogene_onduleur',    'label_affiche' => 'Groupe élec. / Onduleur',              'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'compteur_eau_type',              'label_affiche' => 'Compteur d\'eau',                      'type_champ' => 'enum', 'options_enum' => $optsCompteur, 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'source_eau',                     'label_affiche' => 'Source d\'accès à l\'eau',             'type_champ' => 'enum', 'options_enum' => ['robinet_ville', 'forage_prive', 'chateau_eau'], 'obligatoire' => false, 'est_socle' => false],
            // Confort & connectivité
            ['nom_champ' => 'meuble',                         'label_affiche' => 'Meublé',                               'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'climatisation',                  'label_affiche' => 'Climatisation',                        'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'internet_fibre',                 'label_affiche' => 'Internet / Fibre',                     'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            // Garage — condition frontend: garage_existe = true pour type_vehicule & capacite
            ['nom_champ' => 'garage_existe',                  'label_affiche' => 'Garage disponible',                    'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'garage_type_vehicule',           'label_affiche' => 'Garage : type de véhicules admis',     'type_champ' => 'enum', 'options_enum' => ['deux_roues', 'quatre_roues', 'les_deux'], 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'garage_capacite',                'label_affiche' => 'Garage : capacité (véhicules)',        'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            // Sécurité
            ['nom_champ' => 'securite_gardiennage',           'label_affiche' => 'Sécurité — Gardiennage',               'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'securite_videosurveillance',     'label_affiche' => 'Sécurité — Vidéosurveillance',         'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'securite_alarme',                'label_affiche' => 'Sécurité — Alarme',                    'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'etat_bien',                      'label_affiche' => 'État du bien',                         'type_champ' => 'enum', 'options_enum' => ['neuf', 'bon_etat', 'a_renover'], 'obligatoire' => true, 'est_socle' => true],
        ];

        // ── Sanitaires + compteurs partagés Bureau/Commerce/Entrepôt ────────
        $sharedBCE = [
            ['nom_champ' => 'toilette_existe',            'label_affiche' => 'Toilette / WC existe',                 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            // condition frontend: toilette_existe = true pour les 3 suivants
            ['nom_champ' => 'toilette_emplacement',       'label_affiche' => 'Toilette : interne ou externe',        'type_champ' => 'enum', 'options_enum' => ['interne', 'externe'], 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'toilette_usage',             'label_affiche' => 'Toilette : usage',                    'type_champ' => 'enum', 'options_enum' => ['privee', 'partagee'], 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'nb_toilettes',               'label_affiche' => 'Nombre de toilettes / WC',            'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'compteur_electricite_type',  'label_affiche' => 'Compteur d\'électricité',             'type_champ' => 'enum', 'options_enum' => $optsCompteur, 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'compteur_eau_type',          'label_affiche' => 'Compteur d\'eau',                     'type_champ' => 'enum', 'options_enum' => $optsCompteur, 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'niveau',                     'label_affiche' => 'Niveau / Étage',                      'type_champ' => 'texte', 'obligatoire' => false, 'est_socle' => false],
            // Parking (bureau/commerce/entrepôt = places, pas garage)
            ['nom_champ' => 'parking_visiteur_places',    'label_affiche' => 'Parking visiteur (nb places)',         'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'parking_personnel_places',   'label_affiche' => 'Parking personnel (nb places)',        'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'climatisation',              'label_affiche' => 'Climatisation',                       'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'internet_fibre',             'label_affiche' => 'Internet / Fibre',                    'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'securite_gardiennage',       'label_affiche' => 'Sécurité — Gardiennage',              'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'securite_videosurveillance', 'label_affiche' => 'Sécurité — Vidéosurveillance',        'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'securite_alarme',            'label_affiche' => 'Sécurité — Alarme',                   'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
            ['nom_champ' => 'etat_bien',                  'label_affiche' => 'État du bien',                        'type_champ' => 'enum', 'options_enum' => ['neuf', 'bon_etat', 'a_renover'], 'obligatoire' => true, 'est_socle' => true],
        ];

        return [
            // ── 1. Maison ──────────────────────────────────────────────────────
            [
                'nom' => 'Maison', 'slug' => 'maison',
                'description'             => 'Bien immobilier individuel bâti, loué ou vendu dans son intégralité.',
                'pourcentage_commission'  => 5.00,
                'frais_etude_pourcentage' => 1.50,
                'attributs'              => $attrsMaisonVilla,
            ],
            // ── 2. Villa ───────────────────────────────────────────────────────
            [
                'nom' => 'Villa', 'slug' => 'villa',
                'description'             => 'Villa individuelle bâtie avec jardin, louée ou vendue dans son intégralité.',
                'pourcentage_commission'  => 5.00,
                'frais_etude_pourcentage' => 2.00,
                'attributs'              => $attrsMaisonVilla,
            ],
            // -- 3. Appartement ----------------------------------------------------
            [
                'nom' => 'Appartement', 'slug' => 'appartement',
                'description'             => 'Logement autonome au sein d\'un immeuble.',
                'pourcentage_commission'  => 7.50,
                'frais_etude_pourcentage' => 1.00,
                // NOTE: types_logement supprimes - plus de F1/F2/F3
                'attributs' => [
                    // Chambres
                    ['nom_champ' => 'nb_chambres',               'label_affiche' => 'Nombre de chambres',                  'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => true],
                    ['nom_champ' => 'nb_chambres_sdb_privative', 'label_affiche' => 'Chambres avec WC + Douche privatifs', 'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    // Pieces de vie
                    ['nom_champ' => 'wc_visiteur',               'label_affiche' => 'WC visiteur',                         'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'salon',                     'label_affiche' => 'Salon',                               'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'terrasse',                  'label_affiche' => 'Terrasse / Balcon',                   'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    // WC/Douche externe - condition frontend: wc_douche_externe = true pour wc_douche_externe_usage
                    ['nom_champ' => 'wc_douche_externe',         'label_affiche' => 'WC / Douche externe',                 'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'wc_douche_externe_usage',   'label_affiche' => 'WC/Douche externe : usage',           'type_champ' => 'enum', 'options_enum' => ['commun', 'prive'], 'obligatoire' => false, 'est_socle' => false],
                    // Localisation dans l'immeuble
                    ['nom_champ' => 'situation_immeuble',        'label_affiche' => 'Situation dans l\'immeuble',          'type_champ' => 'enum', 'options_enum' => ['rez_de_chaussee', 'a_l_etage'], 'obligatoire' => false, 'est_socle' => false],
                    // condition frontend: situation_immeuble = 'a_l_etage'
                    ['nom_champ' => 'etage_occupe',              'label_affiche' => 'Quel etage ?',                        'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'nb_etages_immeuble',        'label_affiche' => 'Nombre total d\'etages de l\'immeuble', 'type_champ' => 'nombre', 'obligatoire' => false, 'est_socle' => false],
                    // Structure & equipements
                    ['nom_champ' => 'cuisine',                   'label_affiche' => 'Cuisine',                             'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'magasin_debarras',          'label_affiche' => 'Magasin / Debarras',                  'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    // Electricite & eau
                    ['nom_champ' => 'compteur_electricite_type', 'label_affiche' => 'Compteur d\'electricite',             'type_champ' => 'enum', 'options_enum' => $optsCompteur, 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'compteur_eau_type',         'label_affiche' => 'Compteur d\'eau',                     'type_champ' => 'enum', 'options_enum' => $optsCompteur, 'obligatoire' => false, 'est_socle' => false],
                    // Confort
                    ['nom_champ' => 'meuble',                    'label_affiche' => 'Meuble',                              'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'carrele',                   'label_affiche' => 'Carrele',                             'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'climatisation',             'label_affiche' => 'Climatisation',                       'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'internet_fibre',            'label_affiche' => 'Internet / Fibre',                    'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    // Parking - condition frontend: parking_existe = true pour type_vehicule & capacite
                    ['nom_champ' => 'parking_existe',            'label_affiche' => 'Parking disponible',                  'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'parking_type_vehicule',     'label_affiche' => 'Parking : type de vehicule',          'type_champ' => 'enum', 'options_enum' => ['deux_roues', 'quatre_roues', 'les_deux'], 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'parking_capacite',          'label_affiche' => 'Parking : nombre de places',          'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'etat_bien',                 'label_affiche' => 'Etat du bien',                        'type_champ' => 'enum', 'options_enum' => ['neuf', 'bon_etat', 'a_renover'], 'obligatoire' => true, 'est_socle' => true],
                ],
            ],
            // ── 4. Bureau ──────────────────────────────────────────────────────
            [
                'nom' => 'Bureau', 'slug' => 'bureau',
                'description'             => 'Espace de travail professionnel cloisonné ou open space.',
                'pourcentage_commission'  => 8.00,
                'frais_etude_pourcentage' => 3.00,
                'attributs' => array_merge([
                    ['nom_champ' => 'bureau_configuration',     'label_affiche' => 'Configuration',                         'type_champ' => 'enum', 'options_enum' => ['open_space', 'cloisonne', 'mixte'], 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'bureau_nb_pieces',         'label_affiche' => 'Nombre de pièces / bureaux',            'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => true],
                    ['nom_champ' => 'bureau_salle_reunion',     'label_affiche' => 'Salle de réunion',                     'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'bureau_capacite_postes',   'label_affiche' => 'Capacité (postes de travail)',          'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                ], $sharedBCE),
            ],
            // ── 5. Local Commercial ────────────────────────────────────────────
            [
                'nom' => 'Local commercial', 'slug' => 'commerce',
                'description'             => 'Espace commercial, boutique ou pas-de-porte.',
                'pourcentage_commission'  => 10.00,
                'frais_etude_pourcentage' => 3.50,
                'attributs' => array_merge([
                    ['nom_champ' => 'commerce_vitrine',              'label_affiche' => 'Vitrine sur rue',                      'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'commerce_enseigne_autorisee',   'label_affiche' => 'Enseigne extérieure autorisée',        'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'commerce_reserve',              'label_affiche' => 'Arrière-boutique / Réserve',           'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'commerce_emplacement',          'label_affiche' => 'Type d\'emplacement',                  'type_champ' => 'enum', 'options_enum' => ['avenue_principale', 'rue_secondaire', 'marche', 'autre'], 'obligatoire' => false, 'est_socle' => false],
                ], $sharedBCE),
            ],
            // ── 6. Entrepôt ────────────────────────────────────────────────────
            [
                'nom' => 'Entrepôt', 'slug' => 'entrepot',
                'description'             => 'Bâtiment industriel ou de stockage.',
                'pourcentage_commission'  => 5.00,
                'frais_etude_pourcentage' => 2.50,
                'attributs' => array_merge([
                    ['nom_champ' => 'entrepot_hauteur_plafond',     'label_affiche' => 'Hauteur sous plafond (m)',             'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'entrepot_quai_chargement',     'label_affiche' => 'Quai de chargement',                  'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    // condition frontend: entrepot_quai_chargement = true
                    ['nom_champ' => 'entrepot_quai_nb',             'label_affiche' => 'Nombre de quais',                     'type_champ' => 'nombre',  'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'entrepot_acces_poids_lourd',   'label_affiche' => 'Accès poids lourd',                   'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                    ['nom_champ' => 'entrepot_zone_climatisee',     'label_affiche' => 'Zone climatisée / réfrigérée',        'type_champ' => 'booleen', 'obligatoire' => false, 'est_socle' => false],
                ], $sharedBCE),
            ],
            // ── 7. Terrain ─────────────────────────────────────────────────────
            [
                'nom' => 'Terrain / Parcelle', 'slug' => 'terrain',
                'description'             => 'Bien immobilier non bâti, sans construction.',
                'pourcentage_commission'  => 5.00,
                'frais_etude_pourcentage' => 1.50,
                'attributs' => [
                    ['nom_champ' => 'terrain_usage',       'label_affiche' => 'Type d\'usage autorisé',       'type_champ' => 'enum',   'options_enum' => ['habitation', 'commercial', 'agricole'], 'obligatoire' => true,  'est_socle' => true],
                    ['nom_champ' => 'terrain_mode',        'label_affiche' => 'Mode d\'exploitation',         'type_champ' => 'enum',   'options_enum' => ['bail', 'location'],                     'obligatoire' => true,  'est_socle' => true],
                    ['nom_champ' => 'terrain_duree',       'label_affiche' => 'Durée de mise à disposition', 'type_champ' => 'nombre', 'obligatoire' => true,  'est_socle' => true],
                    ['nom_champ' => 'terrain_duree_unite', 'label_affiche' => 'Unité de la durée',            'type_champ' => 'enum',   'options_enum' => ['annees', 'mois'],                       'obligatoire' => true,  'est_socle' => true],
                ],
            ],
        ];
    }
}
