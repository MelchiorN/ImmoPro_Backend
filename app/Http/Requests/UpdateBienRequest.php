<?php

namespace App\Http\Requests;

use App\Models\Bien;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBienRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Bien $bien */
        $bien = $this->route('bien');

        return $bien->user_id === $this->user()->id
            && $bien->estModifiable();
    }

    public function rules(): array
    {
        $typeBien    = $this->input('type_bien', $this->route('bien')->type_bien);
        $sansChambre = in_array($typeBien, Bien::typeSansChambres());
        $transaction = $this->input('type_transaction', $this->route('bien')->type_transaction);

        return [
            // Infos de base
            'type_bien'        => 'sometimes|string|exists:categories,slug',
            'type_transaction' => ['sometimes', Rule::in(['vente', 'location', 'colocation'])],
            'titre'            => 'sometimes|string|min:5|max:255',
            'prix'             => 'sometimes|numeric|min:0',
            'unite_prix'       => ['sometimes', Rule::in(Bien::UNITES_PRIX)],
            'description'      => 'nullable|string|max:2000',
            'surface'          => 'nullable|numeric|min:1',
            'superficie'       => 'nullable|numeric|min:1',
            'nb_pieces'        => ($sansChambre ? 'nullable' : 'sometimes') . '|integer|min:1|max:100',
            'nb_salles_bain'   => ($sansChambre ? 'nullable' : 'sometimes') . '|integer|min:0|max:50',
            'caracteristiques' => 'nullable|array',
            'adresse'          => 'sometimes|string|max:500',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',

            // Conditions location
            'avance_mois'      => in_array($transaction, ['location', 'colocation'])
                                    ? 'nullable|integer|min:0|max:24'
                                    : 'nullable',
            'caution'          => in_array($transaction, ['location', 'colocation'])
                                    ? 'nullable|numeric|min:0'
                                    : 'nullable',

            // Déposant (modifiable uniquement si rejeté ou brouillon)
            'role_deposant'            => ['sometimes', Rule::in(Bien::ROLES_DEPOSANT)],
            'proprietaire_nom'         => 'sometimes|nullable|string|max:100',
            'proprietaire_prenom'      => 'sometimes|nullable|string|max:100',
            'proprietaire_sexe'        => ['sometimes', 'nullable', Rule::in(['homme', 'femme'])],
            'proprietaire_nationalite' => 'sometimes|nullable|string|max:100',
            'proprietaire_telephone'   => 'sometimes|nullable|string|max:30',
            'proprietaire_email'       => 'sometimes|nullable|email|max:150',
            'proprietaire_adresse'     => 'sometimes|nullable|string|max:500',
        ];
    }
}
