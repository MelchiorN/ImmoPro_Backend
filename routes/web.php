<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route nommée 'login' requise par auth:sanctum pour les redirections d'authentification.
// Retourne du JSON 401 au lieu d'une page web (application API pure).
Route::get('/login', fn () => response()->json([
    'success' => false,
    'message' => 'Non authentifié. Veuillez fournir un token Bearer valide.',
], 401))->name('login');
