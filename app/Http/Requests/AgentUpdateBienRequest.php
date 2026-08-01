<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgentUpdateBienRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // L'autorisation est gérée par le contrôleur
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'titre'            => 'sometimes|string|max:255',
            'description'      => 'sometimes|string',
            'type_transaction' => 'sometimes|string|in:location,vente,colocation',
            'prix'             => 'sometimes|numeric|min:0',
            'adresse'          => 'sometimes|string|max:255',
            'ville'            => 'sometimes|string|max:100',
            'pays'             => 'sometimes|string|max:100',
            'latitude'         => 'sometimes|numeric',
            'longitude'        => 'sometimes|numeric',
            'surface'          => 'sometimes|numeric|min:0',
            'superficie'       => 'sometimes|numeric|min:0',
            'nb_pieces'        => 'sometimes|integer|min:0',
            'nb_salles_bain'   => 'sometimes|integer|min:0',
            'unite_prix'       => 'sometimes|string|max:50',
            'avance_mois'      => 'sometimes|integer|min:0',
            'caution'          => 'sometimes|numeric|min:0',
            'attributs'        => 'sometimes|array',
        ];
    }
}
