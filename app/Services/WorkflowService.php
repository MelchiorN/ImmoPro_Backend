<?php

namespace App\Services;

use App\Models\Bien;
use App\Models\Rapport;
use App\Models\Visite;
use App\Models\ConfigPublication;
use Carbon\Carbon;

/**
 * WorkflowService — Calcule dynamiquement le workflow de progression d'un bien.
 *
 * Le workflow est constitué de 6 étapes fixes dont les statuts sont dérivés
 * des données existantes (biens, visites, rapports) sans table dédiée.
 */
class WorkflowService
{
    // SLA1 par défaut : 2 heures (en minutes) — utilisé si ConfigPublication
    // n'a pas encore de colonne sla1_valeur/sla1_unite.
    private const SLA1_MINUTES_DEFAUT = 120;

    // ─────────────────────────────────────────────────────────────────────────
    // Définition statique des étapes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retourne la définition statique des 6 étapes du workflow.
     * Non modifiable via la BDD (règle métier fixe).
     */
    public static function etapes(): array
    {
        return [
            ['id' => 1, 'titre' => 'Soumission du dossier',   'poids' => 10, 'icone' => 'upload_file'],
            ['id' => 2, 'titre' => 'Prise en charge',         'poids' => 15, 'icone' => 'assignment_ind'],
            ['id' => 3, 'titre' => 'Visite de vérification',  'poids' => 25, 'icone' => 'home_search'],
            ['id' => 4, 'titre' => 'Rédaction du rapport',    'poids' => 25, 'icone' => 'rate_review'],
            ['id' => 5, 'titre' => 'Décision de l\'agent',    'poids' => 15, 'icone' => 'gavel'],
            ['id' => 6, 'titre' => 'Publication / Clôture',   'poids' => 10, 'icone' => 'publish'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Calcul principal
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcule le workflow complet d'un bien.
     *
     * Charge les relations nécessaires si elles ne sont pas encore chargées,
     * puis dérive chaque étape à partir des données existantes.
     */
    public function calculer(Bien $bien): array
    {
        // S'assurer que les relations sont disponibles
        $bien->loadMissing(['rapport', 'agent']);

        // Récupérer la dernière visite de vérification liée à ce bien.
        // Le modèle Visite n'a pas encore de colonne type_visite — on prend
        // simplement la dernière visite de l'agent assigné.
        $derniereVisite = $this->chargerDerniereVisite($bien);

        $rapport = $bien->rapport;

        // Résoudre le délai SLA1 depuis la config (avec fallback sécurisé)
        $sla1Minutes = $this->resoudreSla1Minutes();

        // ── Calculer chaque étape dans l'ordre ───────────────────────────────
        $etape1 = $this->etape1($bien);
        $etape2 = $this->etape2($bien, $sla1Minutes, $etape1);
        $etape3 = $this->etape3($bien, $derniereVisite, $rapport, $etape2);
        $etape4 = $this->etape4($rapport, $etape3);
        $etape5 = $this->etape5($bien, $etape4);
        $etape6 = $this->etape6($bien, $etape5);

        $statuts = [$etape1, $etape2, $etape3, $etape4, $etape5, $etape6];

        // ── Calcul du pourcentage global ─────────────────────────────────────
        $definitions = self::etapes();
        $pourcentage = 0;

        foreach ($statuts as $i => $statut) {
            if ($statut === 'termine') {
                $pourcentage += $definitions[$i]['poids'];
            } elseif ($statut === 'en_cours') {
                // Moitié du poids pour une étape en cours
                $pourcentage += intdiv($definitions[$i]['poids'], 2);
            }
        }

        // ── Construire la réponse enrichie ───────────────────────────────────
        $etapesResult = [];
        foreach ($definitions as $i => $def) {
            $statut         = $statuts[$i];
            $etapesResult[] = [
                'id'      => $def['id'],
                'titre'   => $def['titre'],
                'icone'   => $def['icone'],
                'poids'   => $def['poids'],
                'statut'  => $statut,
                'label'   => $this->labelStatut($statut),
                'couleur' => $this->couleurStatut($statut),
                'detail'  => $this->detailEtape($def['id'], $bien, $derniereVisite, $rapport),
            ];
        }

        return [
            'bien_id'        => $bien->id,
            'bien_titre'     => $bien->titre,
            'bien_adresse'   => $bien->adresse,
            'bien_statut'    => $bien->statut,
            'bien_lat'       => $bien->latitude  ? (float) $bien->latitude  : null,
            'bien_lng'       => $bien->longitude ? (float) $bien->longitude : null,
            'pourcentage'    => $pourcentage,
            'etapes'         => $etapesResult,
            'etape_courante' => $this->etapeCourante($statuts),
            'agent'          => $bien->agent ? [
                'id'    => $bien->agent->id,
                'nom'   => trim("{$bien->agent->first_name} {$bien->agent->last_name}"),
                'email' => $bien->agent->email,
            ] : null,
            'dates'          => [
                'submitted_at' => $bien->submitted_at?->toIso8601String(),
                'claimed_at'   => $bien->claimed_at?->toIso8601String(),
            ],
            'calcule_le'     => now()->toIso8601String(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Calcul individuel de chaque étape
    // ─────────────────────────────────────────────────────────────────────────

    private function etape1(Bien $bien): string
    {
        // Un bien en brouillon n'a pas encore été soumis
        if ($bien->statut === 'brouillon') {
            return 'en_cours';
        }

        // Tout autre statut (en_attente, en_cours, valide, publie, rejete, archive)
        // signifie que le dossier a été soumis
        return 'termine';
    }

    private function etape2(Bien $bien, int $sla1Minutes, string $etape1): string
    {
        if ($etape1 !== 'termine') {
            return 'non_commence';
        }

        // Agent assigné → étape terminée
        if ($bien->agent_id) {
            return 'termine';
        }

        // Vérifier si le SLA1 est dépassé (pas encore d'agent assigné)
        $reference = $bien->submitted_at
            ? Carbon::parse($bien->submitted_at)
            : $bien->created_at;

        if ($reference && now()->diffInMinutes($reference, true) > $sla1Minutes) {
            return 'bloque'; // Délai de prise en charge dépassé
        }

        return 'en_cours'; // En attente d'un agent
    }

    private function etape3(Bien $bien, ?Visite $visite, ?Rapport $rapport, string $etape2): string
    {
        if ($etape2 !== 'termine') {
            return 'non_commence';
        }

        // Si un rapport existe, l'agent a déjà avancé au-delà de la visite
        if ($rapport) {
            return 'termine';
        }

        // Visite confirmée → étape terminée
        if ($visite && $visite->statut === 'confirmee') {
            return 'termine';
        }

        // Visite annulée sans nouveau créneau → bloqué
        if ($visite && $visite->statut === 'annulee') {
            return 'bloque';
        }

        // En attente de visite (planifiée ou pas encore créée)
        return 'en_cours';
    }

    private function etape4(?Rapport $rapport, string $etape3): string
    {
        if ($etape3 !== 'termine') {
            return 'non_commence';
        }

        if (! $rapport) {
            return 'en_cours'; // Rapport pas encore rédigé
        }

        // Décision prise sur le rapport → étape terminée
        if (in_array($rapport->statut, [Rapport::STATUT_VALIDE, Rapport::STATUT_REJETE])) {
            return 'termine';
        }

        return 'en_cours'; // Brouillon ou soumis en attente de décision
    }

    private function etape5(Bien $bien, string $etape4): string
    {
        if ($etape4 !== 'termine') {
            return 'non_commence';
        }

        if (in_array($bien->statut, ['valide', 'rejete'])) {
            return 'termine';
        }

        return 'en_cours'; // Décision de l'admin en attente
    }

    private function etape6(Bien $bien, string $etape5): string
    {
        if ($etape5 !== 'termine') {
            return 'non_commence';
        }

        if (in_array($bien->statut, ['publie', 'archive'])) {
            return 'termine';
        }

        // Bien rejeté → ne sera pas publié
        if ($bien->statut === 'rejete') {
            return 'bloque';
        }

        // Bien validé mais pas encore publié
        return 'en_cours';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retourne le numéro de l'étape courante (en_cours ou bloque).
     * Retourne 6 si tout est terminé.
     */
    private function etapeCourante(array $statuts): int
    {
        foreach ($statuts as $i => $statut) {
            if (in_array($statut, ['en_cours', 'bloque'])) {
                return $i + 1;
            }
        }

        return 6;
    }

    /** Libellé lisible du statut d'une étape. */
    private function labelStatut(string $statut): string
    {
        return match ($statut) {
            'non_commence' => 'Non commencé',
            'en_cours'     => 'En cours',
            'termine'      => 'Terminé',
            'bloque'       => 'Bloqué',
            default        => $statut,
        };
    }

    /** Couleur associée au statut d'une étape (pour le front). */
    private function couleurStatut(string $statut): string
    {
        return match ($statut) {
            'non_commence' => 'gray',
            'en_cours'     => 'blue',
            'termine'      => 'green',
            'bloque'       => 'red',
            default        => 'gray',
        };
    }

    /**
     * Génère un texte de détail contextuel pour chaque étape.
     */
    private function detailEtape(int $etapeId, Bien $bien, ?Visite $visite, ?Rapport $rapport): ?string
    {
        return match ($etapeId) {
            1 => $bien->submitted_at
                    ? 'Soumis le ' . Carbon::parse($bien->submitted_at)
                        ->locale('fr')->isoFormat('D MMM YYYY [à] HH[h]mm')
                    : 'Dossier en cours de création',

            2 => $bien->agent
                    ? 'Pris en charge par ' . trim("{$bien->agent->first_name} {$bien->agent->last_name}")
                      . ($bien->claimed_at
                            ? ' le ' . Carbon::parse($bien->claimed_at)->locale('fr')->isoFormat('D MMM YYYY')
                            : '')
                    : 'En attente d\'un agent',

            3 => $visite
                    ? match ($visite->statut) {
                        'confirmee' => 'Visite confirmée le ' . Carbon::parse($visite->date_visite)
                                          ->locale('fr')->isoFormat('D MMM YYYY [à] HH[h]mm'),
                        'annulee'   => 'Visite annulée — nouveau créneau requis',
                        'proposee'  => 'Créneaux proposés — en attente de confirmation',
                        default     => 'Visite planifiée',
                    }
                    : 'Visite à planifier',

            4 => $rapport
                    ? match ($rapport->statut) {
                        Rapport::STATUT_BROUILLON => 'Rapport en cours de rédaction',
                        Rapport::STATUT_SOUMIS    => 'Rapport soumis — en attente de décision',
                        Rapport::STATUT_VALIDE    => 'Rapport approuvé',
                        Rapport::STATUT_REJETE    => 'Rapport rejeté — corrections requises',
                        default                   => 'Rapport en cours',
                    }
                    : 'Rapport à rédiger',

            5 => match ($bien->statut) {
                    'valide' => "Approuvé par l'agent",
                    'rejete' => 'Rejeté — ' . ($bien->note_admin ?? 'voir rapport'),
                    default  => 'Décision en attente',
                 },

            6 => match ($bien->statut) {
                    'publie'  => 'Publié' . ($bien->publie_le
                                    ? ' le ' . Carbon::parse($bien->publie_le)
                                        ->locale('fr')->isoFormat('D MMM YYYY')
                                    : ''),
                    'archive' => 'Archivé',
                    'rejete'  => 'Bien rejeté — non publié',
                    default   => 'En attente de publication',
                 },

            default => null,
        };
    }

    /**
     * Charge la dernière visite pertinente pour un bien.
     *
     * Tente d'abord un filtre sur type_visite='verification' si la colonne
     * existe dans le schéma. Retombe sur la dernière visite toutes catégories
     * si la colonne n'existe pas encore (rétro-compatibilité).
     */
    private function chargerDerniereVisite(Bien $bien): ?Visite
    {
        try {
            // Essayer avec le filtre type_visite si la colonne existe
            $visite = Visite::where('bien_id', $bien->id)
                ->where('type_visite', 'verification')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($visite) {
                return $visite;
            }
        } catch (\Throwable) {
            // La colonne type_visite n'existe pas encore — pas d'erreur
        }

        // Fallback : dernière visite sans filtre de type
        return Visite::where('bien_id', $bien->id)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Résout le délai SLA1 en minutes depuis la configuration.
     *
     * Utilise la valeur en BDD si les colonnes sla1_valeur / sla1_unite
     * existent sur config_publication, sinon retombe sur la constante par défaut.
     */
    private function resoudreSla1Minutes(): int
    {
        try {
            $config = ConfigPublication::instance();

            // Si la config expose des champs SLA
            $valeur = $config->sla1_valeur ?? null;
            $unite  = $config->sla1_unite  ?? null;

            if ($valeur !== null) {
                return $this->uniteEnMinutes((int) $valeur, (string) $unite);
            }
        } catch (\Throwable) {
            // Pas de colonnes SLA dans la config — on utilise la valeur par défaut
        }

        return self::SLA1_MINUTES_DEFAUT;
    }

    /** Convertit une valeur + unité en minutes. */
    private function uniteEnMinutes(int $valeur, string $unite): int
    {
        return match ($unite) {
            'minutes' => $valeur,
            'heures'  => $valeur * 60,
            'jours'   => $valeur * 60 * 24,
            default   => $valeur * 60, // fallback heures
        };
    }
}
