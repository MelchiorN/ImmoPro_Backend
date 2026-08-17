<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur pour l'assistant IA Gemini.
 *
 * Routes :
 *   POST /api/ai/chat              → chatbot multi-tours
 *   POST /api/ai/recommandations   → recommandations de biens
 */
class AiController extends Controller
{
    public function __construct(private readonly GeminiService $gemini) {}

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/ai/chat
    // Corps : { message: string, history: [{role, text}, ...] }
    // ─────────────────────────────────────────────────────────────────────────

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message'           => 'required|string|max:2000',
            'history'           => 'nullable|array|max:20',
            'history.*.role'    => 'required|in:user,model',
            'history.*.text'    => 'required|string|max:5000',
        ]);

        $user    = $request->user();
        $message = $request->input('message');
        $history = $request->input('history', []);

        // Contexte utilisateur injecté dans le system prompt
        $userContext = [
            'role'   => $user->role ?? 'utilisateur',
            'prenom' => $user->first_name ?? '',
        ];

        try {
            $reponse = $this->gemini->chat($message, $history, $userContext);

            return response()->json([
                'success'  => true,
                'reponse'  => $reponse,
                'role'     => 'model',
            ]);

        } catch (\Throwable $e) {
            Log::error('[AI Chat] Erreur Gemini', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            // En dev, on retourne le détail de l'erreur pour faciliter le debug
            $detail = app()->isLocal() ? $e->getMessage() : null;

            return response()->json([
                'success' => false,
                'message' => 'L\'assistant IA est temporairement indisponible. Veuillez réessayer.',
                'debug'   => $detail,
            ], 503);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/ai/recommandations
    // Corps : { bien_ids?: [], limit?: 10, lat?: float, lng?: float, rayon_km?: float }
    // ─────────────────────────────────────────────────────────────────────────

    public function recommandations(Request $request): JsonResponse
    {
        $request->validate([
            'limit'    => 'nullable|integer|min:1|max:50',
            'bien_ids' => 'nullable|array|max:50',
            'lat'      => 'nullable|numeric|between:-90,90',
            'lng'      => 'nullable|numeric|between:-180,180',
            'rayon_km' => 'nullable|numeric|between:1,100',
        ]);

        $user  = $request->user();
        $limit = $request->integer('limit', 20);
        $lat   = $request->filled('lat')  ? (float) $request->input('lat')  : null;
        $lng   = $request->filled('lng')  ? (float) $request->input('lng')  : null;
        $rayon = $request->filled('rayon_km') ? (float) $request->input('rayon_km') : 15.0;

        // ── Préférences déclarées ─────────────────────────────────────────────
        $preferences = [];
        if ($user->preference) {
            $preferences = [
                'budget_min'           => $user->preference->budget_min,
                'budget_max'           => $user->preference->budget_max,
                'types_biens_preferes' => $user->preference->types_biens_preferes ?? [],
                'villes_preferees'     => $user->preference->villes_preferees ?? [],
            ];
        }

        // ── Favoris (signal fort d'intention) ─────────────────────────────────
        $favoris = $user->favoris()
            ->select(['biens.id', 'biens.titre', 'biens.type_bien', 'biens.prix', 'biens.adresse'])
            ->orderBy('favoris.created_at', 'desc')
            ->take(5)
            ->get()
            ->toArray();

        // ── Historique de recherche ────────────────────────────────────────────
        $historiqueRecherche = [];
        if (class_exists(\App\Models\HistoriqueRecherche::class)) {
            $historiqueRecherche = $user->historiqueRecherches()
                ->select(['query_text', 'type_bien', 'type_transaction', 'ville', 'prix_min', 'prix_max', 'created_at'])
                ->latest()
                ->take(10)
                ->get()
                ->toArray();
        }

        // ── Localisation GPS actuelle ─────────────────────────────────────────
        $localisationActuelle = null;
        if ($lat !== null && $lng !== null) {
            $localisationActuelle = [
                'lat'      => $lat,
                'lng'      => $lng,
                'rayon_km' => $rayon,
            ];
        }

        // ── Cold start : aucune donnée personnelle ─────────────────────────────
        $hasDonnees = !empty($preferences)
                   || !empty($favoris)
                   || !empty($historiqueRecherche)
                   || $localisationActuelle !== null;

        // ── Requête biens publiés ─────────────────────────────────────────────
        if ($lat !== null && $lng !== null) {
            // Avec filtre géographique Haversine — on inclut la distance dans les données
            $query = Bien::where('statut', 'publie')
                ->selectRaw("
                    id, titre, type_bien, type_transaction,
                    prix, unite_prix, surface, adresse,
                    nb_pieces, caracteristiques, latitude, longitude,
                    publie_le,
                    (6371 * acos(
                        cos(radians(?)) * cos(radians(latitude))
                        * cos(radians(longitude) - radians(?))
                        + sin(radians(?)) * sin(radians(latitude))
                    )) AS distance_km
                ", [$lat, $lng, $lat])
                ->having('distance_km', '<=', $rayon)
                ->orderBy('distance_km');
        } else {
            $query = Bien::where('statut', 'publie')
                ->select([
                    'id', 'titre', 'type_bien', 'type_transaction',
                    'prix', 'unite_prix', 'surface', 'adresse',
                    'nb_pieces', 'caracteristiques', 'latitude', 'longitude',
                    'publie_le',
                ])
                ->latest('publie_le');
        }

        $query->limit($limit);

        if ($request->filled('bien_ids')) {
            $query->whereIn('id', $request->input('bien_ids'));
        }

        // Filtre léger sur budget si préférences existent
        if (!empty($preferences['budget_max'])) {
            $query->where('prix', '<=', $preferences['budget_max'] * 1.25);
        }

        $biens = $query->get()->map(fn ($b) => [
            'id'               => $b->id,
            'titre'            => $b->titre,
            'type_bien'        => $b->type_bien,
            'type_transaction' => $b->type_transaction,
            'prix'             => (float) $b->prix,
            'unite_prix'       => $b->unite_prix,
            'surface'          => (float) $b->surface,
            'adresse'          => $b->adresse,
            'nb_pieces'        => $b->nb_pieces,
            'publie_le'        => $b->publie_le,
            'distance_km'      => isset($b->distance_km) ? round((float) $b->distance_km, 2) : null,
        ])->toArray();

        if (empty($biens)) {
            return response()->json([
                'success'         => true,
                'recommandations' => [],
                'message'         => 'Aucun bien disponible dans votre zone pour le moment.',
            ]);
        }

        // ── Rediriger vers cold start si aucune donnée personnelle ───────────
        if (!$hasDonnees) {
            return $this->recommandationsColdStart($biens);
        }

        try {
            $reponseRaw = $this->gemini->recommander(
                $biens,
                $preferences,
                $favoris,
                $historiqueRecherche,
                $localisationActuelle
            );

            $parsed = $this->parseJsonResponse($reponseRaw);
            $recs   = $parsed['recommandations'] ?? [];

            if (empty($recs)) {
                $recs = $this->genererRecommandationsLocales($biens, $preferences, $favoris, $lat, $lng);
            }

            $bienIds      = array_column($recs, 'bien_id');
            $biensDetails = Bien::whereIn('id', $bienIds)->with(['medias'])->get()->keyBy('id');

            $recommandationsEnrichies = array_values(array_filter(array_map(function ($rec) use ($biensDetails) {
                $b = $biensDetails->get($rec['bien_id']);
                if (!$b) return null;
                $rec['bien'] = $b->toArray();
                return $rec;
            }, $recs)));

            return response()->json([
                'success'         => true,
                'source'          => !empty($parsed['recommandations']) ? 'gemini_ia' : 'local_fallback',
                'recommandations' => $recommandationsEnrichies,
                'message'         => $parsed['message'] ?? 'Voici nos recommandations personnalisées pour vous.',
            ]);

        } catch (\Throwable $e) {
            Log::warning('[AI Recommandations] Gemini indisponible, fallback local activé', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success'         => true,
                'source'          => 'local_fallback',
                'recommandations' => $this->genererRecommandationsLocales($biens, $preferences, $favoris, $lat, $lng),
                'message'         => 'Recommandations calculées selon vos préférences.',
            ]);
        }
    }

    /**
     * Cold start : recommandations pour un utilisateur sans aucune donnée personnelle.
     * Stratégie : 60% popularité globale (nb favoris) + 40% fraîcheur (date publication).
     */
    private function recommandationsColdStart(array $biens): JsonResponse
    {
        $bienIds    = array_column($biens, 'id');
        $popularite = \App\Models\Favori::whereIn('bien_id', $bienIds)
            ->selectRaw('bien_id, COUNT(*) as nb_favoris')
            ->groupBy('bien_id')
            ->pluck('nb_favoris', 'bien_id')
            ->toArray();

        $maxFavoris = !empty($popularite) ? max($popularite) : 1;
        $now        = now();

        $scored = [];
        foreach ($biens as $bien) {
            $joursPub       = $bien['publie_le']
                ? \Carbon\Carbon::parse($bien['publie_le'])->diffInDays($now)
                : 60;
            $scoreFraicheur  = max(0.3, 1 - ($joursPub / 60) * 0.7);
            $nbFavoris       = $popularite[$bien['id']] ?? 0;
            $scorePopularite = $maxFavoris > 0 ? ($nbFavoris / $maxFavoris) : 0;
            $score           = round(0.6 * $scorePopularite + 0.4 * $scoreFraicheur, 2);
            $score           = max(0.50, min(0.95, $score));

            $scored[] = [
                'bien_id' => $bien['id'],
                'score'   => $score,
                'raison'  => $nbFavoris > 0
                    ? "{$nbFavoris} personne(s) ont mis ce bien en favori — très apprécié du moment."
                    : 'Bien récemment publié susceptible de vous intéresser.',
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top5 = array_slice($scored, 0, 5);

        $biensDetails = Bien::whereIn('id', array_column($top5, 'bien_id'))
            ->with(['medias'])
            ->get()->keyBy('id');

        $enrichis = array_values(array_filter(array_map(function ($rec) use ($biensDetails) {
            $b = $biensDetails->get($rec['bien_id']);
            if (!$b) return null;
            $rec['bien'] = $b->toArray();
            return $rec;
        }, $top5)));

        return response()->json([
            'success'         => true,
            'source'          => 'cold_start_popularity',
            'recommandations' => $enrichis,
            'message'         => 'Voici les biens les plus appréciés du moment sur ImmoPro.',
        ]);
    }

    /**
     * Moteur de scoring local sécurisé (Weighted Recommendation Engine).
     * Utilisé comme fallback si Gemini est indisponible ou s'il n'y a pas de quota.
     * Critères : budget (30%), type bien (20%), localisation préférences (15%),
     *            similarité favoris (15%), proximité GPS (25% si dispo), surface (10%).
     */
    private function genererRecommandationsLocales(
        array  $biens,
        array  $preferences,
        array  $favoris = [],
        ?float $userLat = null,
        ?float $userLng = null
    ): array
    {
        $scored = [];

        $budgetMax  = $preferences['budget_max'] ?? null;
        $budgetMin  = $preferences['budget_min'] ?? null;
        $typesPref  = array_map('strtolower', $preferences['types_biens_preferes'] ?? []);
        $villesPref = $preferences['villes_preferees'] ?? [];

        // Extraire les signatures des favoris pour la comparaison de similarité
        $favoriTypes  = array_map('strtolower', array_column($favoris, 'type_bien'));
        $favoriPrix   = array_filter(array_column($favoris, 'prix'));
        $avgFavoriPrix = !empty($favoriPrix) ? array_sum($favoriPrix) / count($favoriPrix) : null;

        foreach ($biens as $bien) {
            $score   = 0.45; // Score de base
            $raisons = [];

            // Critère 1 : Budget (30%)
            if ($budgetMax && $bien['prix'] <= $budgetMax) {
                $score += 0.30;
                $raisons[] = 'Prix dans votre budget';
            } elseif ($budgetMax && $bien['prix'] <= $budgetMax * 1.15) {
                $score += 0.12;
                $raisons[] = 'Prix légèrement au-dessus de votre budget';
            }

            // Critère 2 : Type de bien (20%)
            if (!empty($typesPref) && in_array(strtolower($bien['type_bien'] ?? ''), $typesPref)) {
                $score += 0.20;
                $raisons[] = 'Type de bien correspondant à vos préférences';
            }

            // Critère 3 : Localisation déclarée dans les préférences (15%)
            if (!empty($villesPref) && !empty($bien['adresse'])) {
                foreach ($villesPref as $v) {
                    if (stripos($bien['adresse'], $v) !== false) {
                        $score += 0.15;
                        $raisons[] = "Situé à {$v}, une de vos villes préférées";
                        break;
                    }
                }
            }

            // Critère 4 : Similarité avec les favoris (15%)
            if (!empty($favoriTypes) && in_array(strtolower($bien['type_bien'] ?? ''), $favoriTypes)) {
                $score += 0.10;
                $raisons[] = 'Même type que vos biens favoris';

                // Bonus si la gamme de prix est similaire (±30%)
                if ($avgFavoriPrix && $bien['prix'] > 0) {
                    $ratio = $bien['prix'] / $avgFavoriPrix;
                    if ($ratio >= 0.70 && $ratio <= 1.30) {
                        $score += 0.05;
                        $raisons[] = 'Gamme de prix similaire à vos favoris';
                    }
                }
            }

            // Critère 5 : Proximité GPS (25% si coordonnées dispo)
            if ($userLat !== null && $userLng !== null && isset($bien['distance_km']) && $bien['distance_km'] !== null) {
                $dist = (float) $bien['distance_km'];
                if ($dist <= 3) {
                    $score += 0.25;
                    $raisons[] = 'Très proche de vous (' . round($dist, 1) . ' km)';
                } elseif ($dist <= 8) {
                    $score += 0.18;
                    $raisons[] = 'À ' . round($dist, 1) . ' km de votre position';
                } elseif ($dist <= 15) {
                    $score += 0.10;
                    $raisons[] = 'À ' . round($dist, 1) . ' km de votre position';
                }
            }

            $score = min(0.98, round($score, 2));

            $scored[] = [
                'bien_id' => $bien['id'],
                'score'   => $score,
                'raison'  => !empty($raisons)
                    ? implode(', ', $raisons) . '.'
                    : 'Bien récent susceptible de vous intéresser.',
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, 5);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/ai/generer-description
    // Corps : { bien_id?: string, description_brute?: string, bien_data?: array }
    // ─────────────────────────────────────────────────────────────────────────

    public function genererDescription(Request $request, \App\Services\BienDescriptionService $ruleService): JsonResponse
    {
        $request->validate([
            'bien_id'           => 'nullable|string|exists:biens,id',
            'description_brute' => 'nullable|string|max:2000',
            'bien_data'         => 'nullable|array',
        ]);

        $bienId           = $request->input('bien_id');
        $descriptionBrute = $request->input('description_brute', '');
        $bienData         = $request->input('bien_data', []);

        $bienModel = null;
        if ($bienId) {
            $bienModel = Bien::find($bienId);
            if ($bienModel) {
                // ── Retourner la description mise en cache si disponible ──────
                // Évite un appel Gemini inutile si la desc a déjà été générée à l'approbation.
                if (!empty($bienModel->desc_personnalisee)) {
                    return response()->json([
                        'success'     => true,
                        'source'      => 'cached',
                        'description' => $bienModel->desc_personnalisee,
                    ]);
                }
                $bienData = array_merge($bienModel->toArray(), $bienData);
            }
        }

        // Étape 1 : Si aucune description brute n'est passée, générer une base par règles
        if (empty($descriptionBrute) && $bienModel) {
            $descriptionBrute = $ruleService->construire($bienModel);
        }

        try {
            // Étape 2 : Tenter la génération enrichie avec Gemini IA
            $descriptionEnrichie = $this->gemini->enrichirDescription($descriptionBrute, $bienData);

            // Mettre en cache sur le bien si on en a un (évite les appels futurs)
            if ($bienModel && empty($bienModel->desc_personnalisee)) {
                $bienModel->update(['desc_personnalisee' => $descriptionEnrichie]);
            }

            return response()->json([
                'success'     => true,
                'source'      => 'gemini_ia',
                'description' => $descriptionEnrichie,
            ]);

        } catch (\Throwable $e) {
            Log::warning('[AI Description] Gemini indisponible, fallback vers règles', [
                'error' => $e->getMessage(),
            ]);

            // Fallback Sécurisé : Si l'IA échoue, on retourne la description basée sur les règles
            $fallbackDesc = !empty($descriptionBrute) 
                ? $descriptionBrute 
                : ($bienModel ? $ruleService->construire($bienModel) : 'Bien immobilier de qualité.');

            return response()->json([
                'success'     => true,
                'source'      => 'rule_fallback',
                'description' => $fallbackDesc,
                'message'     => 'Description générée en mode secours.',
            ]);
        }
    }


    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/ai/ping  (local/debug uniquement)
    // Test rapide : vérifie que la clé API Gemini est valide
    // ─────────────────────────────────────────────────────────────────────────

    public function ping(): JsonResponse
    {
        if (!app()->isLocal()) {
            return response()->json(['message' => 'Non disponible en production.'], 403);
        }

        $apiKey = config('services.gemini.api_key');
        $model  = config('services.gemini.model');

        // Vérifications préliminaires
        $checks = [
            'key_configured'  => !empty($apiKey),
            'key_format_ok'   => str_starts_with($apiKey ?? '', 'AIzaSy'),
            'model_configured'=> !empty($model),
            'key_prefix'      => $apiKey ? substr($apiKey, 0, 10) . '...' : 'NON DÉFINIE',
            'model'           => $model,
        ];

        if (!$checks['key_configured']) {
            return response()->json([
                'success' => false,
                'error'   => 'GEMINI_API_KEY non définie dans .env',
                'checks'  => $checks,
            ], 500);
        }

        // Test avec les deux formats de clés (standard AIzaSy... et auth AQ.xxx)
        $isAuthKey = !str_starts_with($apiKey, 'AIzaSy');

        // Tester un appel réel minimal
        try {
            $reponse = $this->gemini->chat('Réponds juste "OK" pour tester la connexion.', [], []);
            return response()->json([
                'success'  => true,
                'message'  => 'Connexion Gemini OK',
                'response' => substr($reponse, 0, 100),
                'checks'   => $checks,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
                'checks'  => $checks,
            ], 500);
        }
    }

    /**
     * Extrait le JSON d'une réponse Gemini qui peut contenir du texte autour.
     */
    private function parseJsonResponse(string $raw): array
    {
        // Gemini peut entourer le JSON de backticks (```json ... ```)
        $clean = preg_replace('/```json\s*/i', '', $raw);
        $clean = preg_replace('/```\s*/i', '', $clean ?? $raw);
        $clean = trim($clean ?? $raw);

        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            // Si Gemini n'a pas retourné du JSON valide, retourner une structure vide
            Log::warning('[AI] Réponse non-JSON de Gemini', ['raw' => $raw]);
            return ['recommandations' => [], 'message' => $raw];
        }

        return $decoded;
    }
}
