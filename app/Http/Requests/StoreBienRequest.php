<?php

namespace App\Http\Requests;

use App\Models\Bien;
use Illuminate\Foundation\Http\FormRequest;

class StoreBienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth:sanctum vérifié au niveau route
    }

    public function rules(): array
    {
        $typeBien = $this->input('type_bien');
        $sansChambre = in_array($typeBien, Bien::typeSansChambres());

        return [
            // ── Champs obligatoires ────────────────────────────────────────
            'type_bien'        => 'required|in:appartement,maison,villa,terrain,bureau_commerce',
            'type_transaction' => 'required|in:vente,location,colocation',
            'titre'            => 'required|string|min:5|max:255',
            'prix'             => 'required|numeric|min:0',
            'adresse'          => 'required|string|max:500',
            'latitude'         => 'required|numeric|between:-90,90',
            'longitude'        => 'required|numeric|between:-180,180',

            // ── Champs conditionnels ───────────────────────────────────────
            'surface'          => 'nullable|numeric|min:1',
            'description'      => 'nullable|string|max:2000',

            // Pièces/SDB : requis uniquement si le type a des chambres
            'nb_pieces'        => ($sansChambre ? 'nullable' : 'required') . '|integer|min:1|max:100',
            'nb_salles_bain'   => ($sansChambre ? 'nullable' : 'required') . '|integer|min:0|max:50',

            // ── Médias ─────────────────────────────────────────────────────
            'medias'           => 'required|array|min:3|max:10',
            'medias.*'         => 'required|file|mimes:jpg,jpeg,png,webp,mp4,mov,avi|max:51200',

            // ── Documents ──────────────────────────────────────────────────
            'documents'                 => 'required|array',
            'documents.titre_foncier'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.piece_identite'  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.plan_cadastral'  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'type_bien.required'               => 'Le type de bien est obligatoire.',
            'type_bien.in'                      => 'Type de bien invalide.',
            'type_transaction.required'         => 'Le type de transaction est obligatoire.',
            'titre.required'                    => 'Le titre est obligatoire.',
            'titre.min'                         => 'Le titre doit contenir au moins 5 caractères.',
            'prix.required'                     => 'Le prix est obligatoire.',
            'prix.numeric'                      => 'Le prix doit être un nombre.',
            'adresse.required'                  => 'L\'adresse est obligatoire.',
            'latitude.required'                 => 'La localisation GPS est obligatoire.',
            'longitude.required'                => 'La localisation GPS est obligatoire.',
            'nb_pieces.required'                => 'Le nombre de pièces est obligatoire pour ce type de bien.',
            'nb_salles_bain.required'           => 'Le nombre de salles de bain est obligatoire.',
            'medias.required'                   => 'Au moins 3 photos sont obligatoires.',
            'medias.min'                        => 'Au minimum 3 médias sont requis.',
            'medias.max'                        => 'Maximum 10 médias autorisés.',
            'medias.*.mimes'                    => 'Format média invalide. Acceptés : jpg, png, webp, mp4, mov.',
            'medias.*.max'                      => 'Chaque média ne doit pas dépasser 50 Mo.',
            'documents.titre_foncier.required'  => 'Le titre foncier est obligatoire.',
            'documents.titre_foncier.mimes'     => 'Format invalide pour le titre foncier. Acceptés : pdf, jpg, png.',
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
            'nb_pieces'        => 'nombre de pièces',
            'nb_salles_bain'   => 'nombre de salles de bain',
            'adresse'          => 'adresse',
            'latitude'         => 'latitude',
            'longitude'        => 'longitude',
        ];
    }
}
