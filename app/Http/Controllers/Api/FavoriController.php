<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FavoriController extends Controller
{
    /**
     * Liste des biens en favoris pour l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $favoris = $user->favoris()
                        ->with(['mediasPrincipaux', 'categorie'])
                        ->latest('favoris.created_at')
                        ->get();

        return response()->json([
            'success' => true,
            'data' => $favoris
        ]);
    }

    /**
     * Ajoute ou retire un bien des favoris (Toggle).
     */
    public function toggle(Request $request, $id)
    {
        $user = $request->user();
        
        $bien = Bien::findOrFail($id);

        $isFavori = $user->favoris()->where('bien_id', $bien->id)->exists();

        if ($isFavori) {
            $user->favoris()->detach($bien->id);
            $message = 'Bien retiré des favoris.';
            $status = false;
        } else {
            $user->favoris()->attach($bien->id, ['id' => Str::uuid()]);
            $message = 'Bien ajouté aux favoris.';
            $status = true;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_favorite' => $status
        ]);
    }
}
