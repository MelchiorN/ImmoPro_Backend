<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DocumentLegalSeeder
 *
 * Charge les documents légaux par défaut d'ImmoPro :
 *  - CGU  (Conditions Générales d'Utilisation)
 *  - CGV  (Conditions Générales de Vente / Frais de service)
 *  - Politique de confidentialité
 *  - À propos de ImmoPro
 *
 * Terminologie officielle :
 *   • Annonceur : utilisateur qui publie un bien
 *   • Explorateur : utilisateur qui recherche un bien
 *   • ImmoPro ne se présente jamais comme agence — c'est une plateforme de mise en relation.
 */
class DocumentLegalSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [

            // ─────────────────────────────────────────────────────────────────
            // 1. Conditions Générales d'Utilisation (CGU)
            // ─────────────────────────────────────────────────────────────────
            [
                'slug'        => 'cgu',
                'titre'       => "Conditions Générales d'Utilisation",
                'description' => "Règles d'utilisation de la plateforme ImmoPro applicables à tous les utilisateurs.",
                'contenu'     => <<<'MARKDOWN'
# Conditions Générales d'Utilisation

**Dernière mise à jour :** voir date affichée en bas de page.

---

## Article 1 — Objet et nature du service

ImmoPro est une plateforme numérique de mise en relation entre **Annonceurs** et **Explorateurs** dans le cadre de la recherche d'un bien immobilier.

ImmoPro n'est ni agence immobilière, ni mandataire, ni partie au contrat qui pourrait être conclu entre un Annonceur et un Explorateur. La négociation, la conclusion du contrat et son exécution se déroulent exclusivement entre les Utilisateurs concernés, en dehors de la plateforme.

---

## Article 2 — Définitions

- **Annonceur** : Utilisateur qui publie sur la plateforme une ou plusieurs annonces relatives à un bien immobilier dont il a la disposition.
- **Explorateur** : Utilisateur qui consulte les annonces et sollicite une mise en relation avec un Annonceur en vue de visiter un bien.
- **Utilisateur** : Toute personne physique ou morale accédant à la plateforme ImmoPro, qu'elle soit Annonceur ou Explorateur.
- **Plateforme** : L'application mobile et le site web édités par ImmoPro.
- **Compte** : Espace personnel créé par un Utilisateur après inscription.

---

## Article 3 — Accès et inscription

L'accès à certaines fonctionnalités nécessite la création d'un Compte. L'Utilisateur s'engage à fournir des informations exactes, complètes et à jour lors de son inscription et à les actualiser en cas de modification.

Chaque Compte est personnel et non cessible. L'Utilisateur est responsable de la confidentialité de ses identifiants et de toute activité réalisée depuis son Compte.

---

## Article 4 — Fiabilité des informations

ImmoPro attache une importance particulière à la fiabilité des informations publiées. Chaque annonce fait l'objet d'une vérification par ImmoPro avant sa mise en ligne, portant notamment sur la cohérence des informations déclarées et des justificatifs fournis par l'Annonceur.

Cette vérification vise à limiter la publication d'annonces erronées ou frauduleuses ; elle ne constitue toutefois pas une garantie absolue d'exactitude. La responsabilité des informations déclarées incombe en premier lieu à l'Annonceur.

---

## Article 5 — Obligations des Utilisateurs

Tout Utilisateur s'interdit :

- De publier des informations fausses, mensongères ou frauduleuses ;
- D'utiliser la plateforme à des fins illicites ou contraires à l'ordre public ;
- De contourner le processus de mise en relation d'ImmoPro ;
- De collecter ou stocker les données personnelles d'autres Utilisateurs sans leur consentement ;
- De perturber le fonctionnement normal de la plateforme.

---

## Article 6 — Limitation de responsabilité

ImmoPro met en œuvre les moyens raisonnables pour assurer la fiabilité des annonces et la qualité de la mise en relation. ImmoPro ne saurait toutefois être tenu responsable de l'issue des échanges entre Annonceur et Explorateur après la mise en contact, ni des conditions dans lesquelles un accord serait, le cas échéant, conclu entre eux.

---

## Article 7 — Suspension et résiliation

ImmoPro se réserve le droit de suspendre ou de supprimer tout Compte ne respectant pas les présentes CGU, sans préavis ni indemnité.

---

## Article 8 — Modification des CGU

ImmoPro peut modifier les présentes CGU à tout moment. Les Utilisateurs en seront informés via la plateforme. L'utilisation continuée des services après notification vaut acceptation des nouvelles conditions.

---

## Article 9 — Droit applicable

Les présentes CGU sont soumises au droit en vigueur dans le pays d'exercice d'ImmoPro. Tout litige sera soumis aux tribunaux compétents.

---

*Pour toute question : support@immopro.com*
MARKDOWN,
                'actif'   => true,
                'version' => 1,
            ],

            // ─────────────────────────────────────────────────────────────────
            // 2. Conditions Générales de Vente (CGV / Frais de service)
            // ─────────────────────────────────────────────────────────────────
            [
                'slug'        => 'cgv',
                'titre'       => 'Conditions Générales de Vente',
                'description' => "Conditions applicables aux abonnements Annonceurs et aux frais de mise en relation pour les Explorateurs.",
                'contenu'     => <<<'MARKDOWN'
# Conditions Générales de Vente

**Dernière mise à jour :** voir date affichée en bas de page.

---

## Article 1 — Champ d'application

Les présentes Conditions Générales de Vente (CGV) régissent l'ensemble des transactions réalisées via la plateforme ImmoPro, notamment :

- Les abonnements souscrits par les **Annonceurs** pour publier des annonces ;
- Les frais de visite acquittés par les **Explorateurs** pour accéder à la mise en relation.

---

## Article 2 — Abonnement Annonceur

La publication d'annonces par un Annonceur est soumise à la souscription d'un abonnement payant. Chaque formule d'abonnement précise :

- Le nombre d'annonces pouvant être publiées simultanément ;
- Sa durée de validité ;
- Son tarif.

L'abonnement est réglé via les moyens de paiement mobile disponibles sur la plateforme. Il n'est **ni remboursable ni transférable**, sauf disposition contraire prévue par ImmoPro.

Au terme de la période souscrite, l'Annonceur doit renouveler son abonnement pour continuer à publier ou modifier ses annonces.

---

## Article 3 — Frais de visite et mise en relation

Toute demande de visite d'un bien formulée par un Explorateur est soumise au paiement préalable d'un **frais de visite**, dont le montant est indiqué avant validation de la demande.

Ce frais rémunère le service de mise en relation assuré par ImmoPro. Il ne constitue **ni un acompte sur le bien, ni une garantie locative**, et ne préjuge en rien de la conclusion d'un accord entre les Utilisateurs.

### Processus de mise en contact

Après la visite, l'Explorateur confirme sur la plateforme si celle-ci s'est déroulée de manière satisfaisante. En cas de confirmation positive, ImmoPro transmet à l'Explorateur les coordonnées de contact de l'Annonceur, permettant aux deux Utilisateurs de poursuivre leurs échanges directement.

Le frais de visite **n'est pas remboursable**, y compris en l'absence de suite donnée par l'une ou l'autre des parties après la mise en contact.

---

## Article 4 — Moyens de paiement

ImmoPro accepte les paiements effectués via les services de paiement mobile disponibles sur la plateforme. Toute transaction est sécurisée et traitée par les partenaires de paiement agréés.

---

## Article 5 — Facturation et reçus

Un reçu électronique est généré automatiquement pour chaque transaction réalisée sur la plateforme. Il est accessible depuis l'historique de paiements de l'Utilisateur.

---

## Article 6 — Politique de remboursement

Sauf disposition légale contraire ou décision expresse d'ImmoPro, les paiements effectués sur la plateforme ne sont **pas remboursables**. En cas de litige, l'Utilisateur peut contacter le service client à l'adresse : **support@immopro.com**.

---

## Article 7 — Modification des tarifs

ImmoPro se réserve le droit de modifier ses tarifs à tout moment. Les nouvelles conditions tarifaires s'appliquent aux transactions postérieures à leur entrée en vigueur, communiquée aux Utilisateurs via la plateforme.

---

*Pour toute question : support@immopro.com*
MARKDOWN,
                'actif'   => true,
                'version' => 1,
            ],

            // ─────────────────────────────────────────────────────────────────
            // 3. Politique de Confidentialité
            // ─────────────────────────────────────────────────────────────────
            [
                'slug'        => 'politique_confidentialite',
                'titre'       => 'Politique de Confidentialité',
                'description' => "Comment ImmoPro collecte, utilise et protège vos données personnelles.",
                'contenu'     => <<<'MARKDOWN'
# Politique de Confidentialité

**Dernière mise à jour :** voir date affichée en bas de page.

---

## Article 1 — Responsable du traitement

ImmoPro est responsable du traitement des données personnelles collectées via la plateforme. Pour toute demande relative à vos données, contactez-nous à : **support@immopro.com**.

---

## Article 2 — Données collectées

Dans le cadre de l'utilisation de la plateforme, ImmoPro est susceptible de collecter les données suivantes :

**Données d'identification :**
- Nom, prénom
- Adresse e-mail
- Numéro de téléphone
- Pays et ville de résidence

**Données relatives aux biens (Annonceurs) :**
- Informations descriptives du bien
- Documents justificatifs (pièces d'identité, justificatifs de propriété)
- Photos et médias du bien

**Données de navigation et d'utilisation :**
- Adresse IP, type d'appareil, système d'exploitation
- Historique de navigation sur la plateforme
- Données de connexion

**Données financières :**
- Historique des transactions
- Reçus de paiement

---

## Article 3 — Finalités du traitement

Les données collectées sont utilisées pour :

- **Créer et gérer votre Compte** ;
- **Publier et vérifier les annonces** des Annonceurs ;
- **Mettre en relation** Annonceurs et Explorateurs ;
- **Traiter les paiements** et générer les reçus ;
- **Envoyer des notifications** liées à l'utilisation du service ;
- **Assurer la sécurité** de la plateforme et prévenir les fraudes ;
- **Améliorer nos services** via des analyses statistiques anonymisées.

---

## Article 4 — Bases légales du traitement

Les traitements reposent sur les bases légales suivantes :

- **Exécution du contrat** : traitement nécessaire à la fourniture du service ;
- **Intérêt légitime** : sécurisation, prévention des fraudes, amélioration du service ;
- **Consentement** : communications marketing (désactivable à tout moment).

---

## Article 5 — Partage des données

ImmoPro ne vend ni ne loue vos données personnelles à des tiers. Les données peuvent être partagées avec :

- Nos **partenaires de paiement** (traitement sécurisé des transactions) ;
- Nos **prestataires techniques** (hébergement, envoi d'e-mails) dans le cadre strict de leurs missions ;
- Les **autorités compétentes** si la loi l'exige.

Dans le cadre de la **mise en relation**, les coordonnées de l'Annonceur sont transmises à l'Explorateur uniquement après confirmation positive d'une visite.

---

## Article 6 — Durée de conservation

- **Données de Compte actif** : conservées pendant toute la durée d'utilisation du service ;
- **Données après suppression du Compte** : conservées 3 ans à des fins légales et de sécurité ;
- **Données financières** : conservées 10 ans conformément aux obligations légales.

---

## Article 7 — Vos droits

Conformément à la réglementation en vigueur, vous disposez des droits suivants :

- **Droit d'accès** : obtenir une copie de vos données ;
- **Droit de rectification** : corriger des données inexactes ;
- **Droit à l'effacement** : demander la suppression de vos données ;
- **Droit d'opposition** : vous opposer à certains traitements ;
- **Droit à la portabilité** : recevoir vos données dans un format structuré.

Pour exercer ces droits, contactez-nous à : **support@immopro.com**

---

## Article 8 — Sécurité

ImmoPro met en œuvre des mesures techniques et organisationnelles adaptées pour protéger vos données contre tout accès non autorisé, modification, divulgation ou destruction.

---

## Article 9 — Cookies et traceurs

La plateforme mobile n'utilise pas de cookies au sens traditionnel. Des données locales sont stockées de manière sécurisée sur votre appareil pour assurer le fonctionnement de l'application (session de connexion, préférences).

---

## Article 10 — Modifications

ImmoPro peut modifier la présente politique à tout moment. Vous en serez informé via la plateforme. La version en vigueur est celle accessible sur l'application.

---

*Pour toute question : support@immopro.com*
MARKDOWN,
                'actif'   => true,
                'version' => 1,
            ],

            // ─────────────────────────────────────────────────────────────────
            // 4. À propos de ImmoPro
            // ─────────────────────────────────────────────────────────────────
            [
                'slug'        => 'a_propos',
                'titre'       => 'À propos de ImmoPro',
                'description' => "Qui sommes-nous et quelle est notre mission.",
                'contenu'     => <<<'MARKDOWN'
# À propos de ImmoPro

---

## Notre mission

ImmoPro est une plateforme numérique qui simplifie et sécurise la mise en relation entre propriétaires et personnes en recherche de bien immobilier.

Notre ambition : rendre la recherche immobilière **transparente, fiable et accessible** à tous, en s'appuyant sur un processus de vérification rigoureux de chaque annonce avant sa mise en ligne.

---

## Comment ça marche ?

### Pour les Annonceurs
Vous êtes propriétaire ou avez la disposition d'un bien ? Créez votre annonce, fournissez les justificatifs requis, et notre équipe s'assure de la cohérence des informations avant publication. Vos annonces atteignent des milliers d'Explorateurs qualifiés.

### Pour les Explorateurs
Vous recherchez un bien ? Parcourez les annonces vérifiées, demandez une visite et rencontrez l'Annonceur en toute confiance. Après une visite confirmée, vous obtenez directement les coordonnées de l'Annonceur pour poursuivre vos échanges.

---

## Nos engagements

- **Fiabilité** : chaque annonce est vérifiée par notre équipe avant mise en ligne ;
- **Transparence** : les frais et conditions sont affichés clairement avant toute transaction ;
- **Sécurité** : vos données et paiements sont protégés par des systèmes sécurisés ;
- **Réactivité** : notre équipe reste disponible pour vous accompagner.

---

## Contact

📧 **Email** : support@immopro.com

---

*ImmoPro — Plateforme de mise en relation immobilière.*
MARKDOWN,
                'actif'   => true,
                'version' => 1,
            ],
        ];

        foreach ($documents as $doc) {
            DB::table('documents_legaux')->updateOrInsert(
                ['slug' => $doc['slug']],
                array_merge($doc, [
                    'date_maj'   => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
