<?php

namespace App\Http\Requests;

use App\Models\AttributDefinition;
use App\Models\Bien;
use App\Models\Categorie;
use App\Models\ConfigPublication;
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
        $sansChambre = in_array($typeBien, Bien::typeSansChambres());
        $role        = $this->input('role_deposant', 'proprietaire');
        $transaction = $this->input('type_transaction');

        $rules = [
            // ── Champs obligatoires ───────────────────────────────────────────
            'type_bien'         => 'required|string|exists:categories,slug',
            'type_transaction'  => ['required', Rule::in(['vente', 'location', 'colocation'])],
            'titre'             => 'required|string|min:5|max:255',
            'prix'              => 'required|numeric|min:0',
            'unite_prix'        => ['required', Rule::in(Bien::UNITES_PRIX)],
            'adresse'           => 'required|string|max:500',
            'latitude'          => 'nullable|numeric|between:-90,90',
            'longitude'         => 'nullable|numeric|between:-180,180',

            // ── Champs conditionnels ──────────────────────────────────────────
            'surface'           => 'nullable|numeric|min:1',
            'superficie'        => 'nullable|numeric|min:1',
            'description'       => 'nullable|string|max:2000',

            // Pièces : requis seulement pour les biens résidentiels
            'nb_pieces'         => ($sansChambre ? 'nullable' : 'required') . '|integer|min:1|max:100',
            'nb_salles_bain'    => ($sansChambre ? 'nullable' : 'required') . '|integer|min:0|max:50',

            // ── Conditions location ───────────────────────────────────────────
            'avance_mois'       => in_array($transaction, ['location', 'colocation'])
                                    ? 'nullable|integer|min:0|max:24'
                                    : 'nullable',
            'caution'           => in_array($transaction, ['location', 'colocation'])
                                    ? 'nullable|numeric|min:0'
                                    : 'nullable',

            // ── Identité déposant ─────────────────────────────────────────────
            'role_deposant'     => ['required', Rule::in(Bien::ROLES_DEPOSANT)],

            // ── Caractéristiques dynamiques ───────────────────────────────────
            'caracteristiques'  => 'nullable|array',

            // ── Médias ────────────────────────────────────────────────────────
            'medias'            => 'required|array|min:3|max:20',
            'medias.*'          => 'required|file|mimes:jpg,jpeg,png,webp,mp4,mov,avi|max:51200',

            // ── Documents — base ──────────────────────────────────────────────
            'documents'                        => 'required|array',
            'documents.justificatif_propriete' => 'required|file|mimes:pdf|max:10240',
        ];

        // ── Documents selon le rôle ───────────────────────────────────────────
        switch ($role) {
            case 'proprietaire':
                $rules['documents.piece_identite'] = 'required|file|mimes:pdf|max:10240';
                break;

            case 'agence':
                $rules['documents.mandat_gestion']        = 'required|file|mimes:pdf|max:10240';
                $rules['documents.piece_identite_deposant'] = 'required|file|mimes:pdf|max:10240';
                break;

            case 'mandataire':
                $rules['documents.procuration']           = 'required|file|mimes:pdf|max:10240';
                $rules['documents.piece_identite_deposant'] = 'required|file|mimes:pdf|max:10240';
                break;

            case 'heritier':
                $rules['documents.acte_succession']       = 'required|file|mimes:pdf|max:10240';
                $rules['documents.piece_identite_deposant'] = 'required|file|mimes:pdf|max:10240';
                break;

            case 'autre':
                $rules['documents.autorisation_ecrite']   = 'nullable|file|mimes:pdf|max:10240';
                $rules['documents.piece_identite_deposant'] = 'required|file|mimes:pdf|max:10240';
                break;
        }

        // ── Infos propriétaire réel (si déposant ≠ propriétaire) ─────────────
        if ($role !== 'proprietaire') {
            $rules['proprietaire_nom']         = 'required|string|max:100';
            $rules['proprietaire_prenom']      = 'required|string|max:100';
            $rules['proprietaire_sexe']        = ['required', Rule::in(['homme', 'femme'])];
            $rules['proprietaire_nationalite'] = 'required|string|max:100';
            $rules['proprietaire_telephone']   = 'required|string|max:30';
            $rules['proprietaire_email']       = 'nullable|email|max:150';
            $rules['proprietaire_adresse']     = 'nullable|string|max:500';
        }

        // ── Validation dynamique des attributs obligatoires de catégorie ──────
        if ($typeBien) {
            $categorie = Categorie::where('slug', $typeBien)->where('actif', true)->first();
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
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'type_bien.required'                      => 'Le type de bien est obligatoire.',
            'type_bien.exists'                        => 'Ce type de bien n\'existe pas dans notre catalogue.',
            'type_transaction.required'               => 'Le type de transaction est obligatoire.',
            'titre.required'                          => 'Le titre est obligatoire.',
            'titre.min'                               => 'Le titre doit contenir au moins 5 caractères.',
            'prix.required'                           => 'Le prix est obligatoire.',
            'prix.numeric'                            => 'Le prix doit être un nombre.',
            'unite_prix.required'                     => 'L\'unité du prix est obligatoire (jour, semaine, mois, annee).',
            'adresse.required'                        => 'L\'adresse est obligatoire.',
            'role_deposant.required'                  => 'Le rôle du déposant est obligatoire.',
            'nb_pieces.required'                      => 'Le nombre de pièces est obligatoire pour ce type de bien.',
            'nb_salles_bain.required'                 => 'Le nombre de salles de bain est obligatoire.',
            'proprietaire_nom.required'               => 'Le nom du propriétaire réel est obligatoire.',
            'proprietaire_prenom.required'            => 'Le prénom du propriétaire réel est obligatoire.',
            'proprietaire_sexe.required'              => 'Le sexe du propriétaire réel est obligatoire.',
            'proprietaire_nationalite.required'       => 'La nationalité du propriétaire réel est obligatoire.',
            'proprietaire_telephone.required'         => 'Le téléphone du propriétaire réel est obligatoire.',
            'medias.required'                         => 'Au moins 3 photos sont obligatoires.',
            'medias.min'                              => 'Au minimum 3 médias sont requis.',
            'medias.max'                              => 'Maximum 20 médias autorisés.',
            'medias.*.mimes'                          => 'Format média invalide. Acceptés : jpg, png, webp, mp4, mov.',
            'medias.*.max'                            => 'Chaque média ne doit pas dépasser 50 Mo.',
            'documents.justificatif_propriete.required' => 'Le justificatif de propriété (PDF) est obligatoire.',
            'documents.piece_identite.required'       => 'La pièce d\'identité (PDF) est obligatoire.',
            'documents.mandat_gestion.required'       => 'Le mandat ou contrat de gestion (PDF) est obligatoire.',
            'documents.procuration.required'          => 'La procuration (PDF) est obligatoire.',
            'documents.acte_succession.required'      => 'L\'acte de succession (PDF) est obligatoire.',
            'documents.piece_identite_deposant.required' => 'La pièce d\'identité du déposant (PDF) est obligatoire.',
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
