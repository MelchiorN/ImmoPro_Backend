<?php

namespace App\Http\Requests;

use App\Models\Bien;
use App\Models\Categorie;
use App\Models\ConfigRoleDeposant;
use App\Models\ConfigTypeTransaction;
use App\Models\ConfigUnitePrix;
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
        $bien        = $this->route('bien');
        $typeBien    = $this->input('type_bien', $bien->type_bien);
        $transaction = $this->input('type_transaction', $bien->type_transaction);

        $categorie = $typeBien ? Categorie::where('slug', $typeBien)->first() : null;
        $aChambres = $categorie ? (bool) $categorie->a_chambres : true;

        $estLocation = $this->resolveEstLocation($transaction);

        return [
            'type_bien'        => ['sometimes', 'string', 'exists:categories,slug'],
            'type_transaction' => ['sometimes', 'string', Rule::in(ConfigTypeTransaction::slugsValides())],
            'titre'            => 'sometimes|string|min:5|max:255',
            'prix'             => 'sometimes|numeric|min:0',
            'unite_prix'       => ['sometimes', 'string', Rule::in(ConfigUnitePrix::slugsValides())],
            'description'      => 'nullable|string|max:2000',
            'surface'          => 'nullable|numeric|min:1',
            'superficie'       => 'nullable|numeric|min:1',
            'nb_pieces'        => ($aChambres ? 'sometimes' : 'nullable') . '|integer|min:1|max:100',
            'nb_salles_bain'   => ($aChambres ? 'sometimes' : 'nullable') . '|integer|min:0|max:50',
            'caracteristiques' => 'nullable|array',
            'adresse'          => 'sometimes|string|max:500',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',

            'avance_mois' => $estLocation ? 'nullable|integer|min:0|max:24' : 'nullable',
            'caution'     => $estLocation ? 'nullable|numeric|min:0'         : 'nullable',

            'role_deposant' => ['sometimes', 'string', Rule::in(ConfigRoleDeposant::slugsValides())],

            // Champs du propriétaire réel — libres en mise à jour
            'proprietaire_nom'         => 'sometimes|nullable|string|max:100',
            'proprietaire_prenom'      => 'sometimes|nullable|string|max:100',
            'proprietaire_sexe'        => 'sometimes|nullable|string|max:20',
            'proprietaire_nationalite' => 'sometimes|nullable|string|max:100',
            'proprietaire_telephone'   => 'sometimes|nullable|string|max:30',
            'proprietaire_email'       => 'sometimes|nullable|email|max:150',
            'proprietaire_adresse'     => 'sometimes|nullable|string|max:500',
        ];
    }

    private function resolveEstLocation(?string $transactionSlug): bool
    {
        if (! $transactionSlug) {
            return false;
        }
        $config = ConfigTypeTransaction::where('slug', $transactionSlug)->first();
        if ($config) {
            return (bool) $config->est_location;
        }
        return in_array($transactionSlug, ['location', 'colocation']);
    }
}
