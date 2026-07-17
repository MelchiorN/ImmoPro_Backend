<?php

namespace App\Http\Requests;

use App\Models\Bien;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBienRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Bien $bien */
        $bien = $this->route('bien');

        // Seul le propriétaire peut modifier, et uniquement si brouillon/rejeté
        return $bien->user_id === $this->user()->id
            && $bien->estModifiable();
    }

    public function rules(): array
    {
        $typeBien    = $this->input('type_bien', $this->route('bien')->type_bien);
        $sansChambre = in_array($typeBien, Bien::typeSansChambres());

        return [
            'type_bien'        => 'sometimes|in:appartement,maison,villa,terrain,bureau_commerce',
            'type_transaction' => 'sometimes|in:vente,location,colocation',
            'titre'            => 'sometimes|string|min:5|max:255',
            'prix'             => 'sometimes|numeric|min:0',
            'description'      => 'nullable|string|max:2000',
            'surface'          => 'nullable|numeric|min:1',
            'superficie'       => 'nullable|numeric|min:1',
            'nb_pieces'        => ($sansChambre ? 'nullable' : 'sometimes') . '|integer|min:1|max:100',
            'nb_salles_bain'   => ($sansChambre ? 'nullable' : 'sometimes') . '|integer|min:0|max:50',
            'adresse'          => 'sometimes|string|max:500',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
        ];
    }
}
