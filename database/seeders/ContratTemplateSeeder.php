<?php

namespace Database\Seeders;

use App\Models\ContratTemplate;
use Illuminate\Database\Seeder;

class ContratTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------------------------
        // Modèle 1 : Bail d'habitation à usage résidentiel (République Togolaise)
        // -------------------------------------------------------------------------
        $templateHabitationHtml = <<<'HTML'
<div class="contract-document" style="font-family: Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; line-height: 1.6;">
    <div style="text-align: center; border-bottom: 2px solid #003E7E; padding-bottom: 16px; margin-bottom: 24px;">
        <h2 style="font-size: 22px; font-weight: 800; color: #003E7E; margin: 0 0 6px 0; text-transform: uppercase;">
            CONTRAT DE BAIL À USAGE D'HABITATION RÉSIDENTIEL
        </h2>
        <p style="font-size: 13px; color: #64748b; margin: 0; font-style: italic;">
            Conforme au Code Foncier et Domanial et aux lois en vigueur en République Togolaise
        </p>
    </div>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #003E7E; text-transform: uppercase; border-left: 4px solid #003E7E; padding-left: 10px; margin-bottom: 12px;">
            I. DÉSIGNATION DES PARTIES
        </h3>
        <p style="margin-bottom: 12px;">Entre les soussignés ci-après désignés :</p>

        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 8px; margin-bottom: 12px;">
            <p style="margin: 0 0 4px 0;"><strong>LE BAILLEUR (Propriétaire / Mandataire) :</strong> {NOM_PROPRIETAIRE}</p>
            <p style="margin: 0;">Téléphone : <strong>{TEL_PROPRIETAIRE}</strong></p>
        </div>

        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 8px;">
            <p style="margin: 0 0 4px 0;"><strong>LE PRENEUR (Locataire) :</strong> {NOM_LOCATAIRE}</p>
            <p style="margin: 0 0 4px 0;">Téléphone : <strong>{TEL_LOCATAIRE}</strong></p>
            <p style="margin: 0;">Email : <strong>{EMAIL_LOCATAIRE}</strong></p>
        </div>
    </section>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #003E7E; text-transform: uppercase; border-left: 4px solid #003E7E; padding-left: 10px; margin-bottom: 12px;">
            II. DÉSIGNATION DU BIEN
        </h3>
        <p style="margin-bottom: 10px;">Le Bailleur donne à bail d'habitation au Preneur qui accepte les locaux désignés comme suit :</p>
        <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 12px 16px; border-radius: 4px;">
            <p style="margin: 0 0 4px 0;"><strong>Bien :</strong> {TITRE_BIEN}</p>
            <p style="margin: 0 0 4px 0;"><strong>Catégorie :</strong> {TYPE_BIEN}</p>
            <p style="margin: 0;"><strong>Adresse / Localisation :</strong> {ADRESSE_BIEN}</p>
        </div>
    </section>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #003E7E; text-transform: uppercase; border-left: 4px solid #003E7E; padding-left: 10px; margin-bottom: 12px;">
            III. DURÉE ET PRISE D'EFFET
        </h3>
        <p>
            Le présent contrat est conclu pour une durée ferme de <strong>{DUREE_MOIS} mois</strong>,
            prenant effet à compter du <strong>{DATE_DEBUT}</strong>.
            À l'échéance, il se renouvelle par tacite reconduction aux mêmes clauses et conditions sauf préavis donné par l'une des parties.
        </p>
    </section>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #003E7E; text-transform: uppercase; border-left: 4px solid #003E7E; padding-left: 10px; margin-bottom: 12px;">
            IV. CONDITIONS FINANCIÈRES
        </h3>
        <ul style="padding-left: 20px; margin: 0;">
            <li style="margin-bottom: 6px;">Loyer mensuel : <strong>{LOYER_MENSUEL}</strong> (frais de gestion et de plateforme inclus).</li>
            <li style="margin-bottom: 6px;">Montant total engagé pour la période : <strong>{TOTAL_LOCATION}</strong>.</li>
            <li>Tous les paiements s'effectuent de façon numérique et sécurisée sur la plateforme ImmoPro (Mobile Money / Carte).</li>
        </ul>
    </section>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #003E7E; text-transform: uppercase; border-left: 4px solid #003E7E; padding-left: 10px; margin-bottom: 12px;">
            V. OBLIGATIONS DES PARTIES
        </h3>
        <ol style="padding-left: 20px; margin: 0;">
            <li style="margin-bottom: 6px;"><strong>Usage :</strong> Les locaux sont exclusivement destinés à l'habitation personnelle du locataire et de sa famille.</li>
            <li style="margin-bottom: 6px;"><strong>Entretien :</strong> Le locataire s'engage à maintenir le logement en bon état d'entretien courant et d'expédier promptement les réparations locatives.</li>
            <li style="margin-bottom: 6px;"><strong>Sous-location :</strong> La sous-location est strictement interdite sauf accord écrit préalable du bailleur.</li>
            <li><strong>Respect des règles :</strong> Le locataire doit respecter la quiétude du voisinage et la réglementation locale.</li>
        </ol>
    </section>

    <div style="margin-top: 32px; padding: 16px; background-color: #f1f5f9; border-radius: 8px; text-align: center; font-size: 12px; color: #475569;">
        Document généré électroniquement et certifié par la plateforme ImmoPro à Lomé (République Togolaise).
    </div>
</div>
HTML;

        // -------------------------------------------------------------------------
        // Modèle 2 : Bail Commercial et Professionnel
        // -------------------------------------------------------------------------
        $templateCommercialHtml = <<<'HTML'
<div class="contract-document" style="font-family: Arial, sans-serif; color: #0f172a; max-width: 800px; margin: 0 auto; line-height: 1.6;">
    <div style="text-align: center; border-bottom: 2px solid #0f766e; padding-bottom: 16px; margin-bottom: 24px;">
        <h2 style="font-size: 22px; font-weight: 800; color: #0f766e; margin: 0 0 6px 0; text-transform: uppercase;">
            CONTRAT DE BAIL COMMERCIAL ET PROFESSIONNEL
        </h2>
        <p style="font-size: 13px; color: #475569; margin: 0; font-style: italic;">
            Régie par l'Acte Uniforme OHADA relatif au droit commercial général
        </p>
    </div>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #0f766e; text-transform: uppercase; border-left: 4px solid #0f766e; padding-left: 10px; margin-bottom: 12px;">
            I. LES PARTIES AU CONTRAT
        </h3>
        <p style="margin-bottom: 10px;">Le présent bail est convenu entre :</p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 12px; border-radius: 6px;">
                <p style="margin: 0 0 4px 0; font-weight: bold; color: #166534;">BAILLEUR :</p>
                <p style="margin: 0;">{NOM_PROPRIETAIRE}</p>
                <p style="margin: 0; font-size: 12px; color: #374151;">Tél : {TEL_PROPRIETAIRE}</p>
            </div>
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 12px; border-radius: 6px;">
                <p style="margin: 0 0 4px 0; font-weight: bold; color: #166534;">PRENEUR (Locataire Commercial) :</p>
                <p style="margin: 0;">{NOM_LOCATAIRE}</p>
                <p style="margin: 0; font-size: 12px; color: #374151;">Tél : {TEL_LOCATAIRE} | Email : {EMAIL_LOCATAIRE}</p>
            </div>
        </div>
    </section>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #0f766e; text-transform: uppercase; border-left: 4px solid #0f766e; padding-left: 10px; margin-bottom: 12px;">
            II. LOCALISATION ET DESTINATION DES LOCAUX
        </h3>
        <p style="margin-bottom: 8px;"><strong>Bien désigné :</strong> {TITRE_BIEN} ({TYPE_BIEN})</p>
        <p style="margin-bottom: 8px;"><strong>Adresse complète :</strong> {ADRESSE_BIEN}</p>
        <p style="margin-bottom: 0;"><strong>Destination des lieux :</strong> Les locaux sont loués pour l'exercice d'une activité commerciale, professionnelle ou administrative conforme aux lois en vigueur.</p>
    </section>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #0f766e; text-transform: uppercase; border-left: 4px solid #0f766e; padding-left: 10px; margin-bottom: 12px;">
            III. DURÉE DU BAIL COMMERCIAL
        </h3>
        <p>
            Le bail commercial est consenti pour une durée de <strong>{DUREE_MOIS} mois</strong> à compter du <strong>{DATE_DEBUT}</strong>.
            Le renouvellement du présent bail obéit aux dispositions impératives de l'Acte Uniforme OHADA.
        </p>
    </section>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #0f766e; text-transform: uppercase; border-left: 4px solid #0f766e; padding-left: 10px; margin-bottom: 12px;">
            IV. CONDITIONS FINANCIÈRES ET REVISION DU LOYER
        </h3>
        <p style="margin-bottom: 6px;">Loyer mensuel contractuel : <strong>{LOYER_MENSUEL}</strong></p>
        <p style="margin-bottom: 6px;">Total pour la période initiale : <strong>{TOTAL_LOCATION}</strong></p>
        <p style="margin-bottom: 0;">Les charges et taxes liées à l'activité professionnelle incombent au Preneur.</p>
    </section>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #0f766e; text-transform: uppercase; border-left: 4px solid #0f766e; padding-left: 10px; margin-bottom: 12px;">
            V. CLAUSE RÉSOLUTOIRE
        </h3>
        <p style="margin: 0;">
            À défaut de paiement d'un seul terme du loyer à son échéance exacte, ou en cas d'inexécution de l'une des clauses du présent bail, le contrat pourra être résilié de plein droit après mise en demeure conforme aux dispositions légales.
        </p>
    </section>

    <div style="margin-top: 30px; padding: 14px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #64748b;">
        Contrat certifié numériquement sur la plateforme ImmoPro.
    </div>
</div>
HTML;

        // -------------------------------------------------------------------------
        // Modèle 3 : Contrat de Location Meublée & Courte Durée
        // -------------------------------------------------------------------------
        $templateMeubleHtml = <<<'HTML'
<div class="contract-document" style="font-family: Arial, sans-serif; color: #1e1b4b; max-width: 800px; margin: 0 auto; line-height: 1.6;">
    <div style="text-align: center; border-bottom: 2px solid #6366f1; padding-bottom: 16px; margin-bottom: 24px;">
        <h2 style="font-size: 22px; font-weight: 800; color: #4338ca; margin: 0 0 6px 0; text-transform: uppercase;">
            CONTRAT DE LOCATION MEUBLÉE ET COURTE DURÉE
        </h2>
        <p style="font-size: 13px; color: #6366f1; margin: 0; font-style: italic;">
            Logement meublé prêt à l'emploi - Plateforme ImmoPro
        </p>
    </div>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #4338ca; text-transform: uppercase; border-left: 4px solid #6366f1; padding-left: 10px; margin-bottom: 12px;">
            1. IDENTIFICATION DES SIGNATAIRES
        </h3>
        <p style="margin-bottom: 10px;">Le présent contrat est conclu entre :</p>
        <div style="background-color: #eef2ff; border: 1px solid #c7d2fe; padding: 14px; border-radius: 8px; margin-bottom: 10px;">
            <p style="margin: 0 0 4px 0;"><strong>Bailleur :</strong> {NOM_PROPRIETAIRE} (Tél : {TEL_PROPRIETAIRE})</p>
            <p style="margin: 0;"><strong>Locataire :</strong> {NOM_LOCATAIRE} (Tél : {TEL_LOCATAIRE} | Email : {EMAIL_LOCATAIRE})</p>
        </div>
    </section>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #4338ca; text-transform: uppercase; border-left: 4px solid #6366f1; padding-left: 10px; margin-bottom: 12px;">
            2. LE LOGEMENT MEUBLÉ ET INVENTAIRE
        </h3>
        <p style="margin-bottom: 6px;"><strong>Titre du bien :</strong> {TITRE_BIEN}</p>
        <p style="margin-bottom: 6px;"><strong>Type :</strong> {TYPE_BIEN}</p>
        <p style="margin-bottom: 6px;"><strong>Adresse :</strong> {ADRESSE_BIEN}</p>
        <p style="margin-bottom: 0; font-style: italic; color: #4338ca;">
            Le logement comprend un mobilier complet garnissant les lieux (literie, équipements électroménagers, vaisselle, salon).
        </p>
    </section>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #4338ca; text-transform: uppercase; border-left: 4px solid #6366f1; padding-left: 10px; margin-bottom: 12px;">
            3. PÉRIODE D'OCCUPATION
        </h3>
        <p>
            La location est accordée pour une période déterminée de <strong>{DUREE_MOIS} mois</strong> à compter du <strong>{DATE_DEBUT}</strong>.
        </p>
    </section>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #4338ca; text-transform: uppercase; border-left: 4px solid #6366f1; padding-left: 10px; margin-bottom: 12px;">
            4. MONTANT ET RÈGLEMENT
        </h3>
        <ul style="padding-left: 20px; margin: 0;">
            <li style="margin-bottom: 6px;">Redevance mensuelle globale : <strong>{LOYER_MENSUEL}</strong>.</li>
            <li style="margin-bottom: 6px;">Total pour l'ensemble du séjour : <strong>{TOTAL_LOCATION}</strong>.</li>
            <li>Le règlement s'effectue intégralement via ImmoPro.</li>
        </ul>
    </section>

    <section style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #4338ca; text-transform: uppercase; border-left: 4px solid #6366f1; padding-left: 10px; margin-bottom: 12px;">
            5. ENGAGEMENTS DU PRENEUR
        </h3>
        <p style="margin-bottom: 6px;">- Conserver les meubles et équipements en parfait état de fonctionnement.</p>
        <p style="margin-bottom: 6px;">- Interdiction stricte de modifier l'agencement ou d'emporter tout élément du mobilier.</p>
        <p style="margin-0;">- Signaler immédiatement tout dommage ou panne survenu dans le logement.</p>
    </section>

    <div style="margin-top: 30px; padding: 14px; background-color: #f5f3ff; border-radius: 8px; text-align: center; font-size: 12px; color: #6d28d9;">
        Document officiel généré automatiquement par ImmoPro.
    </div>
</div>
HTML;

        // Reset legacy active/default flags
        ContratTemplate::query()->update(['est_defaut' => false]);

        // Seed Template 1 (Default)
        ContratTemplate::updateOrCreate(
            ['type' => 'habitation'],
            [
                'titre'        => 'Bail d\'habitation à usage résidentiel (République Togolaise)',
                'description'  => 'Modèle standard officiel pour la location d\'appartements, maisons et villas à usage d\'habitation.',
                'type'         => 'habitation',
                'contenu_html' => $templateHabitationHtml,
                'est_actif'    => true,
                'est_defaut'   => true,
            ]
        );

        // Seed Template 2
        ContratTemplate::updateOrCreate(
            ['type' => 'commercial'],
            [
                'titre'        => 'Bail commercial et à usage professionnel (Acte OHADA)',
                'description'  => 'Modèle recommandé pour les commerces, bureaux, boutiques, magasins et locaux professionnels.',
                'type'         => 'commercial',
                'contenu_html' => $templateCommercialHtml,
                'est_actif'    => true,
                'est_defaut'   => false,
            ]
        );

        // Seed Template 3
        ContratTemplate::updateOrCreate(
            ['type' => 'meuble'],
            [
                'titre'        => 'Contrat de location meublée & courte durée',
                'description'  => 'Modèle adapté aux logements entièrement meublés, résidences d\'hôtes et séjours temporaires.',
                'type'         => 'meuble',
                'contenu_html' => $templateMeubleHtml,
                'est_actif'    => true,
                'est_defaut'   => false,
            ]
        );
    }
}
