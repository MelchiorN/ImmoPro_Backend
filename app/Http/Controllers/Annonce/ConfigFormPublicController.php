<?php

namespace App\Http\Controllers\Annonce;

use App\Http\Controllers\Controller;
use App\Models\Categorie;
use App\Models\ConfigTypeTransaction;
use App\Models\ConfigUnitePrix;
use App\Models\ConfigTypeDocument;
use App\Models\ConfigRoleDeposant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Endpoints PUBLICS pour alimenter le formulaire de publication côté mobile.
 *
 * GET /api/config/formulaire          → tout en un (transactions + unités + roles + docs communs)
 * GET /api/config/transactions        → types de transaction
 * GET /api/config/unites-prix         → unités de prix
 * GET /api/config/types-document      → tous les types de documents
 * GET /api/config/roles-deposant      → rôles avec champs + docs requis
 * GET /api/config/roles-deposant/{slug} → un seul rôle (champs + docs requis)
 */
class ConfigFormPublicController extends Controller
{
    private const CACHE_TTL = 300; // 5 minutes

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/config/formulaire
    // Retourne toute la config en un seul appel (optimisé mobile)
    // ─────────────────────────────────────────────────────────────────────────

    public function full(): JsonResponse
    {
        $data = Cache::remember('config_formulaire_full', self::CACHE_TTL, function () {
            return [
                'transactions'   => $this->buildTransactions(),
                'unites_prix'    => $this->buildUnitesPrix(),
                'roles_deposant' => $this->buildRoles(),
                'types_document' => $this->buildTypesDocument(),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/config/transactions
    // ─────────────────────────────────────────────────────────────────────────

    public function transactions(): JsonResponse
    {
        $data = Cache::remember('config_transactions', self::CACHE_TTL, fn () => $this->buildTransactions());
        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/config/unites-prix
    // ─────────────────────────────────────────────────────────────────────────

    public function unitesPrix(): JsonResponse
    {
        $data = Cache::remember('config_unites_prix', self::CACHE_TTL, fn () => $this->buildUnitesPrix());
        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/config/types-document
    // ─────────────────────────────────────────────────────────────────────────

    public function typesDocument(): JsonResponse
    {
        $data = Cache::remember('config_types_document', self::CACHE_TTL, fn () => $this->buildTypesDocument());
        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/config/roles-deposant
    // ─────────────────────────────────────────────────────────────────────────

    public function roles(): JsonResponse
    {
        $data = Cache::remember('config_roles_deposant', self::CACHE_TTL, fn () => $this->buildRoles());
        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/config/roles-deposant/{slug}
    // Retourne les champs + documents requis pour un rôle donné
    // ─────────────────────────────────────────────────────────────────────────

    public function roleBySlug(string $slug): JsonResponse
    {
        $role = ConfigRoleDeposant::with(['champsDeposant', 'typesDocument'])
            ->where('slug', $slug)
            ->where('actif', true)
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $this->formatRole($role)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Builders internes (utilisés aussi par le cache)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildTransactions(): array
    {
        return ConfigTypeTransaction::actif()->get()->map(fn ($t) => [
            'slug'               => $t->slug,
            'nom'                => $t->nom,
            'est_location'       => $t->est_location,
            'demande_unite_prix' => $t->demande_unite_prix,
        ])->values()->toArray();
    }

    private function buildUnitesPrix(): array
    {
        return ConfigUnitePrix::actif()->get()->map(fn ($u) => [
            'slug' => $u->slug,
            'nom'  => $u->nom,
        ])->values()->toArray();
    }

    private function buildTypesDocument(): array
    {
        return ConfigTypeDocument::actif()->get()->map(fn ($d) => [
            'slug'              => $d->slug,
            'nom'               => $d->nom,
            'description'       => $d->description,
            'formats_acceptes'  => $d->formats_acceptes ?? ['pdf'],
            'taille_max_octets' => $d->taille_max_octets,
            'commun_tous_roles' => $d->commun_tous_roles,
        ])->values()->toArray();
    }

    private function buildRoles(): array
    {
        return ConfigRoleDeposant::with(['champsDeposant', 'typesDocument'])
            ->actif()
            ->get()
            ->map(fn ($r) => $this->formatRole($r))
            ->values()
            ->toArray();
    }

    private function formatRole(ConfigRoleDeposant $r): array
    {
        return [
            'slug'             => $r->slug,
            'nom'              => $r->nom,
            'description'      => $r->description,
            'est_proprietaire' => $r->est_proprietaire,
            'champs'           => $r->champsDeposant->map(fn ($c) => [
                'nom_champ'    => $c->nom_champ,
                'label'        => $c->label,
                'placeholder'  => $c->placeholder,
                'type_champ'   => $c->type_champ,
                'options_enum' => $c->options_enum,
                'obligatoire'  => $c->obligatoire,
            ])->values()->toArray(),
            'documents'        => $r->typesDocument->map(fn ($d) => [
                'slug'        => $d->slug,
                'nom'         => $d->nom,
                'description' => $d->description,
                'obligatoire' => (bool) $d->pivot->obligatoire,
            ])->values()->toArray(),
        ];
    }
}
