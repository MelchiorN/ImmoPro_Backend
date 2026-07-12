<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ClientProfileController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/client/profile
    // Mettre à jour les informations du profil client
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'telephone'  => 'required|string|max:20',
            'country'    => 'required|string|max:100',
            'city'       => 'required|string|max:100',
        ]);

        // Si l'email change, réinitialiser la vérification
        if ($validated['email'] !== $user->email) {
            $validated['email_verified_at'] = null;
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour.',
            'user'    => $this->formatUser($user->fresh()),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/client/password
    // Changer le mot de passe
    // ─────────────────────────────────────────────────────────────────────────

    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mot de passe actuel incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        // Révoquer tous les autres tokens pour forcer la reconnexion sur les autres appareils
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/client/profile/photo
    // Upload de la photo de profil
    // ─────────────────────────────────────────────────────────────────────────

    public function updatePhoto(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120', // 5 Mo max
        ]);

        // Supprimer l'ancienne photo si elle existe
        if ($user->profile_picture) {
            $oldPath = str_replace('/storage/', '', parse_url($user->profile_picture, PHP_URL_PATH));
            \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
        }

        $file   = $request->file('photo');
        $path   = $file->store("profiles/{$user->id}", 'public');
        $url    = \Illuminate\Support\Facades\Storage::disk('public')->url($path);

        $user->update(['profile_picture' => $url]);

        return response()->json([
            'success'         => true,
            'message'         => 'Photo de profil mise à jour.',
            'profile_picture' => $url,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function formatUser(User $user): array
    {
        return [
            'id'                => $user->id,
            'first_name'        => $user->first_name,
            'last_name'         => $user->last_name,
            'email'             => $user->email,
            'telephone'         => $user->telephone,
            'country'           => $user->country,
            'city'              => $user->city,
            'profile_picture'   => $user->profile_picture,
            'role'              => $user->role,
            'status'            => $user->status,
            'email_verified_at' => $user->email_verified_at,
        ];
    }
}
