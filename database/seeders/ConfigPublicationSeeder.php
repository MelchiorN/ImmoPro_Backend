<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Peuple toutes les tables de configuration du formulaire de publication :
 *  - config_types_transaction
 *  - config_unites_prix
 *  - config_types_document
 *  - config_roles_deposant + config_champs_deposant + config_docs_par_role
 *
 * Toutes les valeurs correspondent exactement à ce qui était hardcodé avant.
 */
class ConfigPublicationSeeder extends Seeder
{
    public function run(): void
    {
        // ── Nettoyage (ordre FK) ───────────────────────────────────────────────
        DB::table('config_docs_par_role')->delete();
        DB::table('config_champs_deposant')->delete();
        DB::table('config_roles_deposant')->delete();
        DB::table('config_types_document')->delete();
        DB::table('config_unites_prix')->delete();
        DB::table('config_types_transaction')->delete();

        // ─────────────────────────────────────────────────────────────────────
        // 1. Types de transaction
        // ─────────────────────────────────────────────────────────────────────
        $transactions = [
            ['slug' => 'location',   'nom' => 'Location',   'description' => 'Bien mis en location avec loyer mensuel ou autre périodicité.',     'est_location' => true,  'demande_unite_prix' => true,  'ordre' => 1],
            ['slug' => 'colocation',  'nom' => 'Colocation', 'description' => 'Bien partagé entre plusieurs locataires.',                          'est_location' => true,  'demande_unite_prix' => true,  'ordre' => 2],
            ['slug' => 'vente',       'nom' => 'Vente',      'description' => 'Bien proposé à l\'achat, transaction unique.',                      'est_location' => false, 'demande_unite_prix' => false, 'ordre' => 3],
        ];

        foreach ($transactions as $t) {
            DB::table('config_types_transaction')->insert([
                'id'                 => (string) Str::uuid(),
                'slug'               => $t['slug'],
                'nom'                => $t['nom'],
                'description'        => $t['description'],
                'est_location'       => $t['est_location'],
                'demande_unite_prix' => $t['demande_unite_prix'],
                'actif'              => true,
                'ordre'              => $t['ordre'],
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        // ─────────────────────────────────────────────────────────────────────
        // 2. Unités de prix
        // ─────────────────────────────────────────────────────────────────────
        $unites = [
            ['slug' => 'jour',     'nom' => 'Par jour',     'ordre' => 1],
            ['slug' => 'semaine',  'nom' => 'Par semaine',  'ordre' => 2],
            ['slug' => 'mois',     'nom' => 'Par mois',     'ordre' => 3],
            ['slug' => 'annee',    'nom' => 'Par an',       'ordre' => 4],
        ];

        foreach ($unites as $u) {
            DB::table('config_unites_prix')->insert([
                'id'         => (string) Str::uuid(),
                'slug'       => $u['slug'],
                'nom'        => $u['nom'],
                'actif'      => true,
                'ordre'      => $u['ordre'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ─────────────────────────────────────────────────────────────────────
        // 3. Types de documents
        // ─────────────────────────────────────────────────────────────────────
        $documents = [
            // Commun à tous les rôles
            [
                'slug'              => 'justificatif_propriete',
                'nom'               => 'Justificatif de propriété',
                'description'       => 'Titre foncier, attestation villageoise, acte de vente…',
                'commun_tous_roles' => true,
                'ordre'             => 1,
            ],
            // Propriétaire
            [
                'slug'              => 'piece_identite',
                'nom'               => 'Pièce d\'identité du propriétaire',
                'description'       => 'CNI, passeport ou titre de séjour valide.',
                'commun_tous_roles' => false,
                'ordre'             => 2,
            ],
            // Déposants non-propriétaires
            [
                'slug'              => 'piece_identite_deposant',
                'nom'               => 'Pièce d\'identité du déposant',
                'description'       => 'CNI, passeport ou titre de séjour de la personne qui dépose.',
                'commun_tous_roles' => false,
                'ordre'             => 3,
            ],
            [
                'slug'              => 'mandat_gestion',
                'nom'               => 'Mandat ou contrat de gestion',
                'description'       => 'Document signé autorisant l\'agence à gérer le bien.',
                'commun_tous_roles' => false,
                'ordre'             => 4,
            ],
            [
                'slug'              => 'procuration',
                'nom'               => 'Procuration signée',
                'description'       => 'Procuration légale du propriétaire au mandataire.',
                'commun_tous_roles' => false,
                'ordre'             => 5,
            ],
            [
                'slug'              => 'acte_succession',
                'nom'               => 'Acte de succession',
                'description'       => 'Document notarié attestant la transmission du bien.',
                'commun_tous_roles' => false,
                'ordre'             => 6,
            ],
            [
                'slug'              => 'autorisation_ecrite',
                'nom'               => 'Autorisation écrite signée',
                'description'       => 'Lettre d\'autorisation du propriétaire réel.',
                'commun_tous_roles' => false,
                'ordre'             => 7,
            ],
            [
                'slug'              => 'plan_cadastral',
                'nom'               => 'Plan cadastral',
                'description'       => 'Plan officiel du terrain établi par le cadastre.',
                'commun_tous_roles' => false,
                'ordre'             => 8,
            ],
            // Anciens types hérités (compatibilité)
            [
                'slug'              => 'titre_foncier',
                'nom'               => 'Titre foncier',
                'description'       => 'Document légal attestant la propriété foncière.',
                'commun_tous_roles' => false,
                'ordre'             => 9,
            ],
        ];

        $docIds = [];
        foreach ($documents as $d) {
            $id = (string) Str::uuid();
            $docIds[$d['slug']] = $id;
            DB::table('config_types_document')->insert([
                'id'                => $id,
                'slug'              => $d['slug'],
                'nom'               => $d['nom'],
                'description'       => $d['description'],
                'formats_acceptes'  => json_encode(['pdf']),
                'taille_max_octets' => 10 * 1024 * 1024, // 10 Mo
                'commun_tous_roles' => $d['commun_tous_roles'],
                'actif'             => true,
                'ordre'             => $d['ordre'],
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        // ─────────────────────────────────────────────────────────────────────
        // 4. Rôles déposant + champs + documents requis
        // ─────────────────────────────────────────────────────────────────────
        $roles = [

            // ── Propriétaire ─────────────────────────────────────────────────
            [
                'slug'             => 'proprietaire',
                'nom'              => 'Propriétaire',
                'description'      => 'Le déposant est lui-même propriétaire du bien.',
                'est_proprietaire' => true,
                'ordre'            => 1,
                'champs'           => [], // Pas de section "propriétaire réel"
                'docs'             => [
                    ['slug' => 'justificatif_propriete', 'obligatoire' => true],
                    ['slug' => 'piece_identite',          'obligatoire' => true],
                ],
            ],

            // ── Agence immobilière ────────────────────────────────────────────
            [
                'slug'             => 'agence',
                'nom'              => 'Agence immobilière',
                'description'      => 'L\'agence dépose au nom du propriétaire qu\'elle représente.',
                'est_proprietaire' => false,
                'ordre'            => 2,
                'champs'           => [
                    ['nom_champ' => 'proprietaire_nom',         'label' => 'Nom du propriétaire',           'type_champ' => 'texte',     'obligatoire' => true,  'ordre' => 1],
                    ['nom_champ' => 'proprietaire_prenom',      'label' => 'Prénom du propriétaire',         'type_champ' => 'texte',     'obligatoire' => true,  'ordre' => 2],
                    ['nom_champ' => 'proprietaire_sexe',        'label' => 'Sexe du propriétaire',           'type_champ' => 'enum',      'options_enum' => ['homme', 'femme'], 'obligatoire' => true,  'ordre' => 3],
                    ['nom_champ' => 'proprietaire_nationalite', 'label' => 'Nationalité du propriétaire',    'type_champ' => 'texte',     'obligatoire' => true,  'ordre' => 4],
                    ['nom_champ' => 'proprietaire_telephone',   'label' => 'Téléphone du propriétaire',      'type_champ' => 'telephone', 'obligatoire' => true,  'ordre' => 5],
                    ['nom_champ' => 'proprietaire_email',       'label' => 'Email du propriétaire',          'type_champ' => 'email',     'obligatoire' => false, 'ordre' => 6],
                    ['nom_champ' => 'proprietaire_adresse',     'label' => 'Adresse du propriétaire',        'type_champ' => 'texte',     'obligatoire' => false, 'ordre' => 7],
                ],
                'docs' => [
                    ['slug' => 'justificatif_propriete',   'obligatoire' => true],
                    ['slug' => 'mandat_gestion',            'obligatoire' => true],
                    ['slug' => 'piece_identite_deposant',   'obligatoire' => true],
                ],
            ],

            // ── Mandataire ────────────────────────────────────────────────────
            [
                'slug'             => 'mandataire',
                'nom'              => 'Mandataire',
                'description'      => 'Personne physique mandatée par le propriétaire.',
                'est_proprietaire' => false,
                'ordre'            => 3,
                'champs'           => [
                    ['nom_champ' => 'proprietaire_nom',         'label' => 'Nom du propriétaire',        'type_champ' => 'texte',     'obligatoire' => true,  'ordre' => 1],
                    ['nom_champ' => 'proprietaire_prenom',      'label' => 'Prénom du propriétaire',     'type_champ' => 'texte',     'obligatoire' => true,  'ordre' => 2],
                    ['nom_champ' => 'proprietaire_sexe',        'label' => 'Sexe du propriétaire',       'type_champ' => 'enum',      'options_enum' => ['homme', 'femme'], 'obligatoire' => true,  'ordre' => 3],
                    ['nom_champ' => 'proprietaire_nationalite', 'label' => 'Nationalité',                'type_champ' => 'texte',     'obligatoire' => true,  'ordre' => 4],
                    ['nom_champ' => 'proprietaire_telephone',   'label' => 'Téléphone du propriétaire',  'type_champ' => 'telephone', 'obligatoire' => true,  'ordre' => 5],
                    ['nom_champ' => 'proprietaire_email',       'label' => 'Email du propriétaire',      'type_champ' => 'email',     'obligatoire' => false, 'ordre' => 6],
                    ['nom_champ' => 'proprietaire_adresse',     'label' => 'Adresse du propriétaire',    'type_champ' => 'texte',     'obligatoire' => false, 'ordre' => 7],
                ],
                'docs' => [
                    ['slug' => 'justificatif_propriete',  'obligatoire' => true],
                    ['slug' => 'procuration',              'obligatoire' => true],
                    ['slug' => 'piece_identite_deposant',  'obligatoire' => true],
                ],
            ],

            // ── Héritier ──────────────────────────────────────────────────────
            [
                'slug'             => 'heritier',
                'nom'              => 'Héritier',
                'description'      => 'Personne ayant hérité du bien suite à un décès.',
                'est_proprietaire' => false,
                'ordre'            => 4,
                'champs'           => [
                    ['nom_champ' => 'proprietaire_nom',         'label' => 'Nom du défunt propriétaire',  'type_champ' => 'texte',     'obligatoire' => true,  'ordre' => 1],
                    ['nom_champ' => 'proprietaire_prenom',      'label' => 'Prénom du défunt',            'type_champ' => 'texte',     'obligatoire' => true,  'ordre' => 2],
                    ['nom_champ' => 'proprietaire_nationalite', 'label' => 'Nationalité du défunt',       'type_champ' => 'texte',     'obligatoire' => false, 'ordre' => 3],
                    ['nom_champ' => 'proprietaire_telephone',   'label' => 'Contact héritier principal',  'type_champ' => 'telephone', 'obligatoire' => false, 'ordre' => 4],
                ],
                'docs' => [
                    ['slug' => 'justificatif_propriete',  'obligatoire' => true],
                    ['slug' => 'acte_succession',          'obligatoire' => true],
                    ['slug' => 'piece_identite_deposant',  'obligatoire' => true],
                ],
            ],

            // ── Autre ─────────────────────────────────────────────────────────
            [
                'slug'             => 'autre',
                'nom'              => 'Autre (frère, ami, voisin…)',
                'description'      => 'Toute personne agissant au nom du propriétaire sans mandat officiel.',
                'est_proprietaire' => false,
                'ordre'            => 5,
                'champs'           => [
                    ['nom_champ' => 'proprietaire_nom',         'label' => 'Nom du propriétaire réel',    'type_champ' => 'texte',     'obligatoire' => true,  'ordre' => 1],
                    ['nom_champ' => 'proprietaire_prenom',      'label' => 'Prénom du propriétaire réel', 'type_champ' => 'texte',     'obligatoire' => true,  'ordre' => 2],
                    ['nom_champ' => 'proprietaire_sexe',        'label' => 'Sexe',                        'type_champ' => 'enum',      'options_enum' => ['homme', 'femme'], 'obligatoire' => true,  'ordre' => 3],
                    ['nom_champ' => 'proprietaire_nationalite', 'label' => 'Nationalité',                 'type_champ' => 'texte',     'obligatoire' => true,  'ordre' => 4],
                    ['nom_champ' => 'proprietaire_telephone',   'label' => 'Téléphone du propriétaire',   'type_champ' => 'telephone', 'obligatoire' => true,  'ordre' => 5],
                    ['nom_champ' => 'proprietaire_email',       'label' => 'Email du propriétaire',       'type_champ' => 'email',     'obligatoire' => false, 'ordre' => 6],
                    ['nom_champ' => 'proprietaire_adresse',     'label' => 'Adresse du propriétaire',     'type_champ' => 'texte',     'obligatoire' => false, 'ordre' => 7],
                ],
                'docs' => [
                    ['slug' => 'justificatif_propriete',  'obligatoire' => true],
                    ['slug' => 'autorisation_ecrite',      'obligatoire' => false],
                    ['slug' => 'piece_identite_deposant',  'obligatoire' => true],
                ],
            ],
        ];

        foreach ($roles as $role) {
            $roleId = (string) Str::uuid();

            DB::table('config_roles_deposant')->insert([
                'id'               => $roleId,
                'slug'             => $role['slug'],
                'nom'              => $role['nom'],
                'description'      => $role['description'],
                'est_proprietaire' => $role['est_proprietaire'],
                'actif'            => true,
                'ordre'            => $role['ordre'],
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Champs personnels
            foreach ($role['champs'] as $champ) {
                DB::table('config_champs_deposant')->insert([
                    'id'           => (string) Str::uuid(),
                    'role_id'      => $roleId,
                    'nom_champ'    => $champ['nom_champ'],
                    'label'        => $champ['label'],
                    'placeholder'  => null,
                    'type_champ'   => $champ['type_champ'],
                    'options_enum' => isset($champ['options_enum']) ? json_encode($champ['options_enum']) : null,
                    'obligatoire'  => $champ['obligatoire'],
                    'actif'        => true,
                    'ordre'        => $champ['ordre'],
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            // Documents requis
            foreach ($role['docs'] as $doc) {
                if (!isset($docIds[$doc['slug']])) {
                    continue;
                }
                DB::table('config_docs_par_role')->insert([
                    'id'               => (string) Str::uuid(),
                    'role_id'          => $roleId,
                    'type_document_id' => $docIds[$doc['slug']],
                    'obligatoire'      => $doc['obligatoire'],
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }

        $this->command->info('✅ Config publication seedée : transactions, unités prix, types documents, rôles déposant.');
    }
}
