<?php

namespace App\Http\Requests;

use App\Models\AttributDefinition;
use App\Models\Bien;
use App\Models\Categorie;
use Illuminate\Foundation\Http\FormRequest;

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

        $rules = [
            // ── Champs obligatoires ────────────────────────────────────────
            'type_bien'        => 'required|in:appartement,maison,villa,terrain,bureau_commerce,chambre_studio',
            'type_transaction' => 'required|in:vente,location,colocation',
            'titre'            => 'required|string|min:5|max:255',
            'prix'             => 'required|numeric|min:0',
            'adresse'          => 'required|string|max:500',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',

            // ── Champs conditionnels ───────────────────────────────────────
            'surface'          => 'nullable|numeric|min:1',
            'superficie'       => 'nullable|numeric|min:1',
            'description'      => 'nullable|string|max:2000',

            // nb_pieces et nb_salles_bain : requis seulement pour appartement/maison/villa
            'nb_pieces'        => ($sansChambre ? 'nullable' : 'required') . '|integer|min:1|max:100',
            'nb_salles_bain'   => ($sansChambre ? 'nullable' : 'required') . '|integer|min:0|max:50',

            // ── Caractéristiques dynamiques ────────────────────────────────
            'caracteristiques' => 'nullable|array',

            // ── Médias ─────────────────────────────────────────────────────
            'medias'           => 'required|array|min:3|max:10',
            'medias.*'         => 'required|file|mimes:jpg,jpeg,png,webp,mp4,mov,avi|max:51200',

            // ── Documents ──────────────────────────────────────────────────
            'documents'                       => 'required|array',
            'documents.piece_identite'        => 'required|file|mimes:pdf|max:10240',
            'documents.justificatif_propriete'=> 'nullable|file|mimes:pdf|max:10240',
            'documents.plan_cadastral'        => 'nullable|file|mimes:pdf|max:10240',
        ];

        // ── Validation dynamique des caracteristiques obligatoires ─────────
        // Charger les attributs obligatoires de la catégorie et les ajouter aux rules
        if ($typeBien) {
            $categorie = Categorie::where('slug', $typeBien)->where('actif', true)->first();
            if ($categorie) {
                $attributsObligatoires = AttributDefinition::where('categorie_id', $categorie->id)
                    ->where('obligatoire', true)
                    ->where('actif', true)
                    ->get();

                foreach ($attributsObligatoires as $attribut) {
                    $key = "caracteristiques.{$attribut->nom_champ}";
                    $rule = 'required';

                    // Ajouter la contrainte de type
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
            'type_bien.in'                             => 'Type de bien invalide.',
            'type_transaction.required'                => 'Le type de transaction est obligatoire.',
            'titre.required'                           => 'Le titre est obligatoire.',
            'titre.min'                                => 'Le titre doit contenir au moins 5 caractères.',
            'prix.required'                            => 'Le prix est obligatoire.',
            'prix.numeric'                             => 'Le prix doit être un nombre.',
            'adresse.required'                         => "L'adresse est obligatoire.",
            'nb_pieces.required'                       => 'Le nombre de pièces est obligatoire pour ce type de bien.',
            'nb_salles_bain.required'                  => 'Le nombre de salles de bain est obligatoire.',
            'medias.required'                          => 'Au moins 3 photos sont obligatoires.',
            'medias.min'                               => 'Au minimum 3 médias sont requis.',
            'medias.max'                               => 'Maximum 10 médias autorisés.',
            'medias.*.mimes'                           => 'Format média invalide. Acceptés : jpg, png, webp, mp4, mov.',
            'medias.*.max'                             => 'Chaque média ne doit pas dépasser 50 Mo.',
            'documents.piece_identite.required'        => "La pièce d'identité est obligatoire.",
            'documents.piece_identite.mimes'           => "Format invalide pour la pièce d'identité. Accepté : pdf.",
        ];
    }

    public function attributes(): array
    {
        return [
            'type_bien'        => 'type de bien',
            'type_transaction' => 'type de transaction',
            'titre'            => 'titre',
            'prix'             => 'prix',
            'surface'          => 'surface',
            'superficie'       => 'superficie',
            'nb_pieces'        => 'nombre de pièces',
            'nb_salles_bain'   => 'nombre de salles de bain',
            'adresse'          => 'adresse',
            'latitude'         => 'latitude',
            'longitude'        => 'longitude',
        ];
    }
}
