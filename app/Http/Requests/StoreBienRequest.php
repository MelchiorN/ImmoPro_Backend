<?php

namespace App\Http\Requests;

use App\Models\AttributDefinition;
use App\Models\Bien;
use App\Models\Categorie;
use App\Models\ConfigDocParRole;
use App\Models\ConfigRoleDeposant;
use App\Models\ConfigTypeDocument;
use App\Models\ConfigTypeTransaction;
use App\Models\ConfigUnitePrix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth:sanctum vérifié au niveau route
    }

    public function rules(): array
    {
        $typeBien    = $this->input('type_bien');
        $transaction = $this->input('type_transaction');

        // ── Catégorie : détermine les flags dynamiques ────────────────────────
        $categorie   = $typeBien ? Categorie::where('slug', $typeBien)->where('actif', true)->first() : null;
        $aChambres   = $categorie ? (bool) $categorie->a_chambres : true;
        $estLocation = $this->resolveEstLocation($transaction);

        // ── Rôle déposant ─────────────────────────────────────────────────────
        $roleSlug    = $this->input('role_deposant', 'proprietaire');
        $role        = ConfigRoleDeposant::with('champsDeposant')->where('slug', $roleSlug)->first();

        $rules = [
            // ── Champs obligatoires ───────────────────────────────────────────
            'type_bien'        => ['required', 'string', 'exists:categories,slug'],
            'type_transaction' => ['required', 'string', Rule::in(ConfigTypeTransaction::slugsValides())],
            'titre'            => 'required|string|min:5|max:255',
            'prix'             => 'required|numeric|min:0',
            'unite_prix'       => ['required', 'string', Rule::in(ConfigUnitePrix::slugsValides())],
            'adresse'          => 'required|string|max:500',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',

            // ── Champs optionnels ─────────────────────────────────────────────
            'surface'          => 'nullable|numeric|min:1',
            'superficie'       => 'nullable|numeric|min:1',
            'description'      => 'nullable|string|max:2000',

            // ── Pièces : conditionnel selon la catégorie ──────────────────────
            'nb_pieces'      => ($aChambres ? 'required' : 'nullable') . '|integer|min:1|max:100',
            'nb_salles_bain' => ($aChambres ? 'required' : 'nullable') . '|integer|min:0|max:50',

            // ── Conditions location ───────────────────────────────────────────
            'avance_mois' => $estLocation ? 'nullable|integer|min:0|max:24'  : 'nullable',
            'caution'     => $estLocation ? 'nullable|numeric|min:0'          : 'nullable',

            // ── Rôle déposant ─────────────────────────────────────────────────
            'role_deposant' => ['required', 'string', Rule::in(ConfigRoleDeposant::slugsValides())],

            // ── Caractéristiques dynamiques ───────────────────────────────────
            'caracteristiques' => 'nullable|array',

            // ── Médias ────────────────────────────────────────────────────────
            'medias'    => 'required|array|min:3|max:20',
            'medias.*'  => 'required|file|mimes:jpg,jpeg,png,webp,mp4,mov,avi|max:51200',

            // ── Documents — base (tableau) ────────────────────────────────────
            'documents' => 'required|array',
        ];

        // ── Champs du propriétaire réel — dynamiques depuis la config ─────────
        if ($role) {
            foreach ($role->champsDeposant as $champ) {
                if (! $champ->actif) {
                    continue;
                }
                $fieldKey  = $champ->nom_champ;
                $baseRule  = $champ->obligatoire ? 'required' : 'nullable';

                $typeRule = match ($champ->type_champ) {
                    'email'     => 'email|max:150',
                    'telephone' => 'string|max:30',
                    'booleen'   => 'boolean',
                    'enum'      => 'string|in:' . implode(',', $champ->options_enum ?? []),
                    default     => 'string|max:255',
                };

                $rules[$fieldKey] = "{$baseRule}|{$typeRule}";
            }
        }

        // ── Documents — requis/optionnels depuis la config par rôle ──────────
        $this->addDocumentRules($rules, $roleSlug, $categorie);

        // ── Attributs dynamiques obligatoires de la catégorie ─────────────────
        if ($categorie) {
            $attributsObligatoires = AttributDefinition::where('categorie_id', $categorie->id)
                ->where('obligatoire', true)
                ->where('actif', true)
                ->get();

            foreach ($attributsObligatoires as $attribut) {
                $key  = "caracteristiques.{$attribut->nom_champ}";
                $rule = 'required';
                $rule .= match ($attribut->type_champ) {
                    'nombre'  => '|numeric',
                    'booleen' => '|boolean',
                    'date'    => '|date',
                    'enum'    => '|in:' . implode(',', $attribut->options_enum ?? []),
                    default   => '|string|max:500',
                };
                $rules[$key] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Construit les règles de validation pour les documents
     * en se basant sur la config BDD (rôle + catégorie).
     */
    private function addDocumentRules(array &$rules, string $roleSlug, ?Categorie $categorie): void
    {
        $role = ConfigRoleDeposant::where('slug', $roleSlug)->first();
        if (! $role) {
            // Fallback : justificatif requis pour tous
            $rules['documents.justificatif_propriete'] = 'required|file|mimes:pdf|max:10240';
            return;
        }

        // Documents liés au rôle (requis ou optionnels)
        $docsRole = ConfigDocParRole::with('typeDocument')
            ->where('role_id', $role->id)
            ->get();

        foreach ($docsRole as $dr) {
            if (! $dr->typeDocument || ! $dr->typeDocument->actif) {
                continue;
            }
            $slug      = $dr->typeDocument->slug;
            $fieldKey  = "documents.{$slug}";
            $maxKo     = $dr->typeDocument->taille_max_octets
                ? intdiv($dr->typeDocument->taille_max_octets, 1024)
                : 10240;
            $formats   = implode(',', $dr->typeDocument->formats_acceptes ?? ['pdf']);
            $baseRule  = $dr->obligatoire ? 'required' : 'nullable';

            $rules[$fieldKey] = "{$baseRule}|file|mimes:{$formats}|max:{$maxKo}";
        }

        // Documents optionnels spécifiques à la catégorie (ex: plan_cadastral pour terrain)
        if ($categorie && ! empty($categorie->documents_optionnels)) {
            foreach ($categorie->documents_optionnels as $docSlug) {
                $fieldKey = "documents.{$docSlug}";
                if (! isset($rules[$fieldKey])) {
                    $typeDoc  = ConfigTypeDocument::where('slug', $docSlug)->where('actif', true)->first();
                    $maxKo    = $typeDoc?->taille_max_octets ? intdiv($typeDoc->taille_max_octets, 1024) : 10240;
                    $formats  = $typeDoc ? implode(',', $typeDoc->formats_acceptes ?? ['pdf']) : 'pdf';
                    $rules[$fieldKey] = "nullable|file|mimes:{$formats}|max:{$maxKo}";
                }
            }
        }
    }

    /**
     * Détermine si le type de transaction est une location
     * en interrogeant la config BDD, avec fallback sur les slugs connus.
     */
    private function resolveEstLocation(?string $transactionSlug): bool
    {
        if (! $transactionSlug) {
            return false;
        }
        $config = ConfigTypeTransaction::where('slug', $transactionSlug)->first();
        if ($config) {
            return (bool) $config->est_location;
        }
        // Fallback si la table n'est pas encore seedée
        return in_array($transactionSlug, ['location', 'colocation']);
    }

    public function messages(): array
    {
        return [
            'type_bien.required'        => 'Le type de bien est obligatoire.',
            'type_bien.exists'          => 'Ce type de bien n\'existe pas dans notre catalogue.',
            'type_transaction.required' => 'Le type de transaction est obligatoire.',
            'type_transaction.in'       => 'Ce type de transaction n\'est pas valide.',
            'titre.required'            => 'Le titre est obligatoire.',
            'titre.min'                 => 'Le titre doit contenir au moins 5 caractères.',
            'prix.required'             => 'Le prix est obligatoire.',
            'prix.numeric'              => 'Le prix doit être un nombre.',
            'unite_prix.required'       => 'L\'unité du prix est obligatoire.',
            'unite_prix.in'             => 'L\'unité du prix n\'est pas valide.',
            'adresse.required'          => 'L\'adresse est obligatoire.',
            'role_deposant.required'    => 'Le rôle du déposant est obligatoire.',
            'role_deposant.in'          => 'Ce rôle de déposant n\'est pas valide.',
            'nb_pieces.required'        => 'Le nombre de pièces est obligatoire pour ce type de bien.',
            'nb_salles_bain.required'   => 'Le nombre de salles de bain est obligatoire.',
            'medias.required'           => 'Au moins 3 photos sont obligatoires.',
            'medias.min'                => 'Au minimum 3 médias sont requis.',
            'medias.max'                => 'Maximum 20 médias autorisés.',
            'medias.*.mimes'            => 'Format média invalide. Acceptés : jpg, png, webp, mp4, mov.',
            'medias.*.max'              => 'Chaque média ne doit pas dépasser 50 Mo.',
        ];
    }

    public function attributes(): array
    {
        return [
            'type_bien'        => 'type de bien',
            'type_transaction' => 'type de transaction',
            'titre'            => 'titre',
            'prix'             => 'prix',
            'unite_prix'       => 'unité du prix',
            'surface'          => 'surface',
            'superficie'       => 'superficie',
            'nb_pieces'        => 'nombre de pièces',
            'nb_salles_bain'   => 'nombre de salles de bain',
            'adresse'          => 'adresse',
            'role_deposant'    => 'rôle du déposant',
        ];
    }
}
