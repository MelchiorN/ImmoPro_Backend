<?php

namespace App\Services;

use App\Models\Bien;

/**
 * Génère une description narrative complète d'un bien immobilier
 * à partir de ses champs et caractéristiques renseignés.
 */
class BienDescriptionService
{
    // ─── Mappings libellés ───────────────────────────────────────────────────

    private const TYPES_BIEN = [
        'appartement'  => 'Appartement',
        'villa'        => 'Villa',
        'maison'       => 'Maison',
        'terrain'      => 'Terrain',
        'bureau'       => 'Bureau',
        'commerce'     => 'Local commercial',
        'entrepot'     => 'Entrepôt',
        'chambre'      => 'Chambre',
        'studio'       => 'Studio',
    ];

    private const TRANSACTIONS = [
        'location' => 'à louer',
        'vente'    => 'à vendre',
    ];

    private const UNITES_PRIX = [
        'jour'    => 'par jour',
        'semaine' => 'par semaine',
        'mois'    => 'par mois',
        'annee'   => 'par an',
    ];

    private const TYPES_APPARTEMENT = [
        'studio' => 'Studio',
        'f1'     => '1 chambre salon',
        'f2'     => '1 chambre salon',
        'f3'     => '2 chambres salon',
        'f4'     => '3 chambres salon',
        'f5'     => '4 chambres salon ou +',
    ];

    private const ETATS_BIEN = [
        'neuf'          => 'neuf',
        'bon_etat'      => 'en bon état',
        'a_renover'     => 'à rénover',
        'en_renovation' => 'en cours de rénovation',
    ];

    private const TYPES_COMPTEUR = [
        'compteur_independant'       => 'compteur indépendant',
        'sous_compteur_mecanique'    => 'sous-compteur mécanique',
        'sous_compteur_cashpower'    => 'sous-compteur CashPower',
        'partage'                    => 'compteur partagé',
        'non_raccorde'               => 'non raccordé',
        'compteur_propre'            => 'compteur propre',
    ];

    private const SITUATIONS_IMMEUBLE = [
        'rez_de_chaussee' => 'au rez-de-chaussée',
        'a_l_etage'       => 'à l\'étage',
        'dernier_etage'   => 'au dernier étage',
        'villa_standalone' => 'en villa indépendante',
    ];

    private const USAGES_TOILETTE = [
        'privee'   => 'interne',
        'partagee' => 'externe / partagée',
    ];

    private const EMPLACEMENTS_TOILETTE = [
        'interne' => 'interne',
        'externe' => 'externe',
    ];

    private const TYPES_PARKING = [
        'deux_roues'   => '2 roues',
        'quatre_roues' => '4 roues',
        'mixte'        => 'mixte',
    ];

    private const TYPES_SOL = [
        'carrele'    => 'carrelé',
        'parquet'    => 'parquet',
        'beton'      => 'béton',
        'marbre'     => 'marbre',
    ];

    public function __construct(private readonly ?GeminiService $gemini = null) {}

    // ─── Point d'entrée ──────────────────────────────────────────────────────

    /**
     * Génère la description narrative du bien.
     * Priorité :
     *   1. desc_personnalisee (générée par Gemini lors de la validation — en cache)
     *   2. Gemini en temps réel (si desc_personnalisee vide et Gemini disponible)
     *   3. description manuelle du propriétaire (fallback)
     *   4. Génération automatique par règles (dernier recours)
     */
    public function generer(Bien $bien): string
    {
        // 1. Description personnalisée déjà générée (mise en cache à la validation)
        if (!empty($bien->desc_personnalisee)) {
            return $bien->desc_personnalisee;
        }

        // 2. Description manuelle du propriétaire
        if (!empty($bien->description)) {
            return $bien->description;
        }

        // 3. Génération automatique par règles
        return $this->construire($bien);
    }

    /**
     * Force la génération automatique par règles (fallback).
     */
    public function construire(Bien $bien): string
    {
        $car = $bien->caracteristiques ?? [];
        $type = $bien->type_bien;

        $parties = [];

        // 1. Phrase d'accroche : type + transaction + localisation
        $parties[] = $this->phraseIntro($bien, $car);

        // 2. Surface et état général
        $details = $this->detailsGeneraux($bien, $car);
        if ($details) {
            $parties[] = $details;
        }

        // 3. Pièces et chambres (appartement, villa, maison, chambre, studio)
        if (in_array($type, ['appartement', 'villa', 'maison', 'chambre', 'studio'])) {
            $pieces = $this->descriptionPieces($bien, $car);
            if ($pieces) {
                $parties[] = $pieces;
            }
        }

        // 4. Sanitaires
        $sanitaires = $this->descriptionSanitaires($car);
        if ($sanitaires) {
            $parties[] = $sanitaires;
        }

        // 5. Espaces communs et équipements
        $equip = $this->descriptionEquipements($car);
        if ($equip) {
            $parties[] = $equip;
        }

        // 6. Parking
        $parking = $this->descriptionParking($car);
        if ($parking) {
            $parties[] = $parking;
        }

        // 7. Compteurs et raccordements
        $compt = $this->descriptionCompteurs($car);
        if ($compt) {
            $parties[] = $compt;
        }

        // 8. Confort et services
        $confort = $this->descriptionConfort($car);
        if ($confort) {
            $parties[] = $confort;
        }

        // 9. Terrain (superficie, usage)
        if (in_array($type, ['terrain'])) {
            $terrain = $this->descriptionTerrain($bien, $car);
            if ($terrain) {
                $parties[] = $terrain;
            }
        }

        // 10. Prix et conditions financières
        $prix = $this->descriptionPrix($bien);
        if ($prix) {
            $parties[] = $prix;
        }

        return implode(' ', array_filter($parties));
    }

    // ─── Blocs de description ────────────────────────────────────────────────

    private function phraseIntro(Bien $bien, array $car): string
    {
        // Brouillons : type_bien ou type_transaction peuvent être null
        $rawType  = $bien->type_bien;
        $rawTrans = $bien->type_transaction;

        $typeBien = $rawType
            ? (self::TYPES_BIEN[$rawType] ?? ucfirst(str_replace('_', ' ', $rawType)))
            : 'Bien';
        $trans    = $rawTrans
            ? (self::TRANSACTIONS[$rawTrans] ?? $rawTrans)
            : '';
        $adresse  = $bien->adresse ?? null;

        // Type d'appartement (F2, studio...)
        $typeAppt = '';
        if ($rawType === 'appartement' && !empty($car['type_appartement'])) {
            $typeAppt = ' de type ' . (self::TYPES_APPARTEMENT[$car['type_appartement']] ?? strtoupper($car['type_appartement']));
        }

        $intro = "{$typeBien}{$typeAppt}";

        if ($adresse) {
            $intro .= " situé(e) à {$adresse}";
        }

        if ($trans) {
            $intro .= ", {$trans}.";
        }

        return $intro;
    }

    private function detailsGeneraux(Bien $bien, array $car): string
    {
        $details = [];

        // Surface habitable
        if ($bien->surface) {
            $details[] = "Surface : " . number_format((float) $bien->surface, 0, ',', ' ') . " m²";
        }

        // Superficie du terrain
        if ($bien->superficie) {
            $details[] = "superficie totale de " . number_format((float) $bien->superficie, 0, ',', ' ') . " m²";
        }

        // Position dans l'immeuble
        if (!empty($car['situation_immeuble'])) {
            $sit = self::SITUATIONS_IMMEUBLE[$car['situation_immeuble']] ?? $car['situation_immeuble'];
            $etOccupe = !empty($car['etage_occupe']) ? " (étage " . $car['etage_occupe'] . ")" : '';
            $nbEtages = !empty($car['nb_etages_immeuble']) ? " dans un immeuble de " . $car['nb_etages_immeuble'] . " étage(s)" : '';
            $details[] = ucfirst($sit) . $etOccupe . $nbEtages;
        }

        // État du bien
        if (!empty($car['etat_bien'])) {
            $etat = self::ETATS_BIEN[$car['etat_bien']] ?? $car['etat_bien'];
            $details[] = "Bien " . $etat;
        }

        return implode('. ', $details) . (count($details) ? '.' : '');
    }

    private function descriptionPieces(Bien $bien, array $car): string
    {
        $parties = [];

        // Nombre total de pièces
        if ($bien->nb_pieces) {
            $parties[] = $bien->nb_pieces . " pièce(s) au total";
        }

        // Chambres
        $nbChambres = (int) ($car['nb_chambres'] ?? 0);
        if ($nbChambres > 0) {
            $detail = "{$nbChambres} chambre(s)";
            $sdbPriv  = (int) ($car['nb_chambres_sdb_privative'] ?? 0);
            $douchPriv = (int) ($car['nb_chambres_douche_privative'] ?? 0);
            $suffixes = [];
            if ($sdbPriv > 0) {
                $suffixes[] = "{$sdbPriv} avec WC douche interne";
            }
            if ($douchPriv > 0) {
                $suffixes[] = "{$douchPriv} avec douche interne";
            }
            if ($suffixes) {
                $detail .= " (dont " . implode(', ', $suffixes) . ")";
            }
            $parties[] = $detail;
        }

        // Salon
        if ($this->estVrai($car, 'salon')) {
            $parties[] = "un salon";
        }

        // Cuisine
        if ($this->estVrai($car, 'cuisine')) {
            $parties[] = "une cuisine";
        }

        // Salle à manger
        if ($this->estVrai($car, 'salle_manger')) {
            $parties[] = "une salle à manger";
        }

        // Bureau / salle de travail
        if ($this->estVrai($car, 'bureau')) {
            $parties[] = "un bureau";
        }

        if (empty($parties)) {
            return '';
        }

        return "Il dispose de : " . implode(', ', $parties) . ".";
    }

    private function descriptionSanitaires(array $car): string
    {
        $parties = [];

        // Salles de bain (niveau bien)
        $nbSdb = (int) ($car['nb_salles_bain'] ?? 0);
        if ($nbSdb > 0) {
            $parties[] = "{$nbSdb} salle(s) de bain";
        }

        // Toilette / WC
        $toiletteExiste = $this->estVrai($car, 'toilette_existe');
        if ($toiletteExiste) {
            $wc = "WC";
            $usage = !empty($car['toilette_usage'])
                ? (' ' . (self::USAGES_TOILETTE[$car['toilette_usage']] ?? $car['toilette_usage']))
                : '';
            $emplacement = !empty($car['toilette_emplacement'])
                ? (' — ' . (self::EMPLACEMENTS_TOILETTE[$car['toilette_emplacement']] ?? $car['toilette_emplacement']))
                : '';
            $parties[] = $wc . $usage . $emplacement;
        }

        if (empty($parties)) {
            return '';
        }

        return "Sanitaires : " . implode(', ', $parties) . ".";
    }

    private function descriptionEquipements(array $car): string
    {
        $espaces = [];
        $equipements = [];

        // Espaces
        if ($this->estVrai($car, 'terrasse')) {
            $espaces[] = "une terrasse";
        }
        if ($this->estVrai($car, 'balcon')) {
            $espaces[] = "un balcon";
        }
        if ($this->estVrai($car, 'jardin')) {
            $espaces[] = "un jardin";
        }
        if ($this->estVrai($car, 'magasin_debarras')) {
            $espaces[] = "un magasin/débarras";
        }
        if ($this->estVrai($car, 'cave')) {
            $espaces[] = "une cave";
        }
        if ($this->estVrai($car, 'piscine')) {
            $espaces[] = "une piscine";
        }

        // Sol
        if (!empty($car['type_sol'])) {
            $sol = self::TYPES_SOL[$car['type_sol']] ?? $car['type_sol'];
            $equipements[] = "sol " . $sol;
        } elseif ($this->estVrai($car, 'carrele')) {
            $equipements[] = "sol carrelé";
        }

        $resultat = [];
        if ($espaces) {
            $resultat[] = "Espaces extérieurs/communs : " . implode(', ', $espaces) . ".";
        }
        if ($equipements) {
            $resultat[] = "Finitions : " . implode(', ', $equipements) . ".";
        }

        return implode(' ', $resultat);
    }

    private function descriptionParking(array $car): string
    {
        if (!$this->estVrai($car, 'parking_existe')) {
            return '';
        }

        $desc = "Parking disponible";
        $type = !empty($car['parking_type_vehicule'])
            ? (self::TYPES_PARKING[$car['parking_type_vehicule']] ?? $car['parking_type_vehicule'])
            : null;
        $capacite = !empty($car['parking_capacite']) ? (int) $car['parking_capacite'] : null;

        if ($type || $capacite) {
            $details = [];
            if ($capacite) {
                $details[] = "pour {$capacite} véhicule(s)";
            }
            if ($type) {
                $details[] = "({$type})";
            }
            $desc .= " " . implode(' ', $details);
        }

        return $desc . ".";
    }

    private function descriptionCompteurs(array $car): string
    {
        $compt = [];

        if (!empty($car['compteur_electricite_type'])) {
            $type = self::TYPES_COMPTEUR[$car['compteur_electricite_type']] ?? $car['compteur_electricite_type'];
            $compt[] = "électricité : {$type}";
        }

        if (!empty($car['compteur_eau_type'])) {
            $type = self::TYPES_COMPTEUR[$car['compteur_eau_type']] ?? $car['compteur_eau_type'];
            $compt[] = "eau : {$type}";
        }

        if (empty($compt)) {
            return '';
        }

        return "Compteurs — " . implode(' | ', $compt) . ".";
    }

    private function descriptionConfort(array $car): string
    {
        $items = [];

        if ($this->estVrai($car, 'meuble')) {
            $items[] = "meublé";
        }
        if ($this->estVrai($car, 'climatisation')) {
            $items[] = "climatisé";
        }
        if ($this->estVrai($car, 'internet_fibre')) {
            $items[] = "fibre optique disponible";
        }
        if ($this->estVrai($car, 'gardiennage')) {
            $items[] = "gardiennage";
        }
        if ($this->estVrai($car, 'generateur')) {
            $items[] = "groupe électrogène";
        }
        if ($this->estVrai($car, 'eau_chaude')) {
            $items[] = "eau chaude";
        }
        if ($this->estVrai($car, 'ascenseur')) {
            $items[] = "ascenseur";
        }
        if ($this->estVrai($car, 'securite')) {
            $items[] = "sécurisé";
        }

        if (empty($items)) {
            return '';
        }

        return "Confort & équipements : " . implode(', ', $items) . ".";
    }

    private function descriptionTerrain(Bien $bien, array $car): string
    {
        $details = [];

        if (!empty($car['usage_terrain'])) {
            $details[] = "Usage : " . $car['usage_terrain'];
        }
        if (!empty($car['viabilise']) && $this->estVrai($car, 'viabilise')) {
            $details[] = "terrain viabilisé";
        }
        if (!empty($car['clotiure']) && $this->estVrai($car, 'clotiure')) {
            $details[] = "clôturé";
        }
        if (!empty($car['titre_foncier']) && $this->estVrai($car, 'titre_foncier')) {
            $details[] = "avec titre foncier";
        }

        return implode('. ', $details) . (count($details) ? '.' : '');
    }

    private function descriptionPrix(Bien $bien): string
    {
        $prix = (float) $bien->prix;
        if ($prix <= 0) {
            return '';
        }

        $montant = number_format($prix, 0, ',', ' ') . ' FCFA';
        $unite   = !empty($bien->unite_prix)
            ? (' ' . (self::UNITES_PRIX[$bien->unite_prix] ?? '/' . $bien->unite_prix))
            : '';

        $conditions = [];

        if ($bien->avance_mois && $bien->avance_mois > 0) {
            $conditions[] = $bien->avance_mois . " mois d'avance";
        }
        if ($bien->caution && (float) $bien->caution > 0) {
            $caution = number_format((float) $bien->caution, 0, ',', ' ');
            $conditions[] = "caution de {$caution} FCFA";
        }

        $ligne = "Prix : {$montant}{$unite}";
        if ($conditions) {
            $ligne .= " — " . implode(', ', $conditions);
        }

        return $ligne . ".";
    }

    /**
     * Vérifie si un champ booléen de caracteristiques est vrai.
     * Accepte true (bool), "true" (string), 1, "1".
     */
    private function estVrai(array $car, string $cle): bool
    {
        if (!isset($car[$cle])) {
            return false;
        }
        $val = $car[$cle];
        return $val === true || $val === 1 || $val === '1' || $val === 'true';
    }

    /**
     * Génère et enregistre la description enrichie via Gemini.
     */
    public function enrichirEtSauvegarder(Bien $bien): ?string
    {
        if (!empty($bien->desc_personnalisee)) {
            return $bien->desc_personnalisee;
        }

        try {
            $descBrute = $this->construire($bien);
            $geminiService = $this->gemini ?? app(GeminiService::class);
            $descPersonnalisee = $geminiService->enrichirDescription($descBrute, $bien->toArray());
            
            $bien->update(['desc_personnalisee' => $descPersonnalisee]);
            
            return $descPersonnalisee;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[BienDescriptionService] Génération Gemini échouée', [
                'bien_id' => $bien->id,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }
}
