<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfigTypeTransaction;
use App\Models\ConfigUnitePrix;
use App\Models\ConfigTypeDocument;
use App\Models\ConfigRoleDeposant;
use App\Models\ConfigChampDeposant;
use App\Models\ConfigDocParRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CRUD admin pour toute la configuration du formulaire de publication.
 *
 * Routes sous /api/admin/config-formulaire/
 */
class ConfigPublicationFormController extends Controller
{
    // ═════════════════════════════════════════════════════════════════════════
    // TYPES DE TRANSACTION
    // GET    /admin/config-formulaire/transactions
    // POST   /admin/config-formulaire/transactions
    // PUT    /admin/config-formulaire/transactions/{id}
    // PATCH  /admin/config-formulaire/transactions/{id}/toggle
    // DELETE /admin/config-formulaire/transactions/{id}
    // ═════════════════════════════════════════════════════════════════════════

    public function indexTransactions(): JsonResponse
    {
        $items = ConfigTypeTransaction::orderBy('ordre')->get()
            ->map(fn ($t) => $this->formatTransaction($t));

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function storeTransaction(Request $request): JsonResponse
    {
        $request->validate([
            'slug'                => 'required|string|max:50|unique:config_types_transaction,slug|regex:/^[a-z0-9_]+$/',
            'nom'                 => 'required|string|max:100',
            'description'         => 'nullable|string|max:255',
            'est_location'        => 'nullable|boolean',
            'demande_unite_prix'  => 'nullable|boolean',
            'ordre'               => 'nullable|integer|min:0',
        ]);

        $item = ConfigTypeTransaction::create([
            'slug'                => $request->slug,
            'nom'                 => $request->nom,
            'description'         => $request->description,
            'est_location'        => $request->boolean('est_location', false),
            'demande_unite_prix'  => $request->boolean('demande_unite_prix', false),
            'actif'               => true,
            'ordre'               => $request->input('ordre', ConfigTypeTransaction::max('ordre') + 1),
        ]);

        return response()->json(['success' => true, 'message' => 'Type de transaction créé.', 'data' => $this->formatTransaction($item)], 201);
    }

    public function updateTransaction(Request $request, string $id): JsonResponse
    {
        $item = ConfigTypeTransaction::findOrFail($id);
        $request->validate([
            'nom'                => 'sometimes|string|max:100',
            'description'        => 'nullable|string|max:255',
            'est_location'       => 'sometimes|boolean',
            'demande_unite_prix' => 'sometimes|boolean',
            'ordre'              => 'sometimes|integer|min:0',
        ]);
        $item->update($request->only(['nom', 'description', 'est_location', 'demande_unite_prix', 'ordre']));

        return response()->json(['success' => true, 'message' => 'Mis à jour.', 'data' => $this->formatTransaction($item->fresh())]);
    }

    public function toggleTransaction(string $id): JsonResponse
    {
        $item = ConfigTypeTransaction::findOrFail($id);
        $item->update(['actif' => !$item->actif]);
        return response()->json(['success' => true, 'message' => $item->actif ? 'Activé.' : 'Désactivé.', 'data' => $this->formatTransaction($item)]);
    }

    public function destroyTransaction(string $id): JsonResponse
    {
        $item = ConfigTypeTransaction::findOrFail($id);
        $item->delete();
        return response()->json(['success' => true, 'message' => 'Supprimé.']);
    }

    private function formatTransaction(ConfigTypeTransaction $t): array
    {
        return [
            'id'                 => $t->id,
            'slug'               => $t->slug,
            'nom'                => $t->nom,
            'description'        => $t->description,
            'est_location'       => $t->est_location,
            'demande_unite_prix' => $t->demande_unite_prix,
            'actif'              => $t->actif,
            'ordre'              => $t->ordre,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // UNITÉS DE PRIX
    // ═════════════════════════════════════════════════════════════════════════

    public function indexUnitesPrix(): JsonResponse
    {
        $items = ConfigUnitePrix::orderBy('ordre')->get()
            ->map(fn ($u) => $this->formatUnitePrix($u));
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function storeUnitePrix(Request $request): JsonResponse
    {
        $request->validate([
            'slug'        => 'required|string|max:50|unique:config_unites_prix,slug|regex:/^[a-z0-9_]+$/',
            'nom'         => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'ordre'       => 'nullable|integer|min:0',
        ]);
        $item = ConfigUnitePrix::create([
            'slug'        => $request->slug,
            'nom'         => $request->nom,
            'description' => $request->description,
            'actif'       => true,
            'ordre'       => $request->input('ordre', ConfigUnitePrix::max('ordre') + 1),
        ]);
        return response()->json(['success' => true, 'message' => 'Unité créée.', 'data' => $this->formatUnitePrix($item)], 201);
    }

    public function updateUnitePrix(Request $request, string $id): JsonResponse
    {
        $item = ConfigUnitePrix::findOrFail($id);
        $request->validate([
            'nom'         => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:255',
            'ordre'       => 'sometimes|integer|min:0',
        ]);
        $item->update($request->only(['nom', 'description', 'ordre']));
        return response()->json(['success' => true, 'message' => 'Mis à jour.', 'data' => $this->formatUnitePrix($item->fresh())]);
    }

    public function toggleUnitePrix(string $id): JsonResponse
    {
        $item = ConfigUnitePrix::findOrFail($id);
        $item->update(['actif' => !$item->actif]);
        return response()->json(['success' => true, 'message' => $item->actif ? 'Activé.' : 'Désactivé.', 'data' => $this->formatUnitePrix($item)]);
    }

    public function destroyUnitePrix(string $id): JsonResponse
    {
        ConfigUnitePrix::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Supprimé.']);
    }

    private function formatUnitePrix(ConfigUnitePrix $u): array
    {
        return ['id' => $u->id, 'slug' => $u->slug, 'nom' => $u->nom, 'description' => $u->description, 'actif' => $u->actif, 'ordre' => $u->ordre];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // TYPES DE DOCUMENTS
    // ═════════════════════════════════════════════════════════════════════════

    public function indexTypesDocument(): JsonResponse
    {
        $items = ConfigTypeDocument::orderBy('ordre')->get()
            ->map(fn ($d) => $this->formatTypeDocument($d));
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function storeTypeDocument(Request $request): JsonResponse
    {
        $request->validate([
            'slug'               => 'required|string|max:100|unique:config_types_document,slug|regex:/^[a-z0-9_]+$/',
            'nom'                => 'required|string|max:150',
            'description'        => 'nullable|string|max:500',
            'formats_acceptes'   => 'nullable|array',
            'formats_acceptes.*' => 'string|max:20',
            'taille_max_octets'  => 'nullable|integer|min:0',
            'commun_tous_roles'  => 'nullable|boolean',
            'ordre'              => 'nullable|integer|min:0',
        ]);

        $item = ConfigTypeDocument::create([
            'slug'               => $request->slug,
            'nom'                => $request->nom,
            'description'        => $request->description,
            'formats_acceptes'   => $request->input('formats_acceptes', ['pdf']),
            'taille_max_octets'  => $request->input('taille_max_octets', 10 * 1024 * 1024),
            'commun_tous_roles'  => $request->boolean('commun_tous_roles', false),
            'actif'              => true,
            'ordre'              => $request->input('ordre', ConfigTypeDocument::max('ordre') + 1),
        ]);

        return response()->json(['success' => true, 'message' => 'Type de document créé.', 'data' => $this->formatTypeDocument($item)], 201);
    }

    public function updateTypeDocument(Request $request, string $id): JsonResponse
    {
        $item = ConfigTypeDocument::findOrFail($id);
        $request->validate([
            'nom'                => 'sometimes|string|max:150',
            'description'        => 'nullable|string|max:500',
            'formats_acceptes'   => 'nullable|array',
            'formats_acceptes.*' => 'string|max:20',
            'taille_max_octets'  => 'nullable|integer|min:0',
            'commun_tous_roles'  => 'sometimes|boolean',
            'ordre'              => 'sometimes|integer|min:0',
        ]);
        $item->update($request->only(['nom', 'description', 'formats_acceptes', 'taille_max_octets', 'commun_tous_roles', 'ordre']));
        return response()->json(['success' => true, 'message' => 'Mis à jour.', 'data' => $this->formatTypeDocument($item->fresh())]);
    }

    public function toggleTypeDocument(string $id): JsonResponse
    {
        $item = ConfigTypeDocument::findOrFail($id);
        $item->update(['actif' => !$item->actif]);
        return response()->json(['success' => true, 'message' => $item->actif ? 'Activé.' : 'Désactivé.', 'data' => $this->formatTypeDocument($item)]);
    }

    public function destroyTypeDocument(string $id): JsonResponse
    {
        $nbUsages = \App\Models\DocumentBien::where('type', ConfigTypeDocument::findOrFail($id)->slug)->count();
        if ($nbUsages > 0) {
            return response()->json(['success' => false, 'message' => "Ce type est utilisé par {$nbUsages} document(s) existant(s). Désactivez-le plutôt."], 422);
        }
        ConfigTypeDocument::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Supprimé.']);
    }

    private function formatTypeDocument(ConfigTypeDocument $d): array
    {
        return [
            'id'                => $d->id,
            'slug'              => $d->slug,
            'nom'               => $d->nom,
            'description'       => $d->description,
            'formats_acceptes'  => $d->formats_acceptes ?? ['pdf'],
            'taille_max_octets' => $d->taille_max_octets,
            'taille_max_label'  => $d->taille_max_octets ? round($d->taille_max_octets / 1024 / 1024, 1) . ' Mo' : null,
            'commun_tous_roles' => $d->commun_tous_roles,
            'actif'             => $d->actif,
            'ordre'             => $d->ordre,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // RÔLES DÉPOSANT
    // GET    /admin/config-formulaire/roles
    // POST   /admin/config-formulaire/roles
    // GET    /admin/config-formulaire/roles/{id}
    // PUT    /admin/config-formulaire/roles/{id}
    // PATCH  /admin/config-formulaire/roles/{id}/toggle
    // DELETE /admin/config-formulaire/roles/{id}
    // ═════════════════════════════════════════════════════════════════════════

    public function indexRoles(): JsonResponse
    {
        $roles = ConfigRoleDeposant::with(['tousLesChamps', 'typesDocument'])->orderBy('ordre')->get()
            ->map(fn ($r) => $this->formatRole($r));
        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function showRole(string $id): JsonResponse
    {
        $role = ConfigRoleDeposant::with(['tousLesChamps', 'typesDocument'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $this->formatRole($role)]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $request->validate([
            'slug'             => 'required|string|max:50|unique:config_roles_deposant,slug|regex:/^[a-z0-9_]+$/',
            'nom'              => 'required|string|max:100',
            'description'      => 'nullable|string|max:500',
            'est_proprietaire' => 'nullable|boolean',
            'ordre'            => 'nullable|integer|min:0',
        ]);
        $role = ConfigRoleDeposant::create([
            'slug'             => $request->slug,
            'nom'              => $request->nom,
            'description'      => $request->description,
            'est_proprietaire' => $request->boolean('est_proprietaire', false),
            'actif'            => true,
            'ordre'            => $request->input('ordre', ConfigRoleDeposant::max('ordre') + 1),
        ]);
        return response()->json(['success' => true, 'message' => 'Rôle créé.', 'data' => $this->formatRole($role->load(['tousLesChamps', 'typesDocument']))], 201);
    }

    public function updateRole(Request $request, string $id): JsonResponse
    {
        $role = ConfigRoleDeposant::findOrFail($id);
        $request->validate([
            'nom'              => 'sometimes|string|max:100',
            'description'      => 'nullable|string|max:500',
            'est_proprietaire' => 'sometimes|boolean',
            'ordre'            => 'sometimes|integer|min:0',
        ]);
        $role->update($request->only(['nom', 'description', 'est_proprietaire', 'ordre']));
        return response()->json(['success' => true, 'message' => 'Rôle mis à jour.', 'data' => $this->formatRole($role->fresh(['tousLesChamps', 'typesDocument']))]);
    }

    public function toggleRole(string $id): JsonResponse
    {
        $role = ConfigRoleDeposant::findOrFail($id);
        $role->update(['actif' => !$role->actif]);
        return response()->json(['success' => true, 'message' => $role->actif ? 'Activé.' : 'Désactivé.']);
    }

    public function destroyRole(string $id): JsonResponse
    {
        $role = ConfigRoleDeposant::findOrFail($id);
        DB::beginTransaction();
        try {
            $role->docsParRole()->delete();
            $role->tousLesChamps()->delete();
            $role->delete();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Rôle supprimé.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur lors de la suppression.'], 500);
        }
    }

    private function formatRole(ConfigRoleDeposant $r): array
    {
        return [
            'id'               => $r->id,
            'slug'             => $r->slug,
            'nom'              => $r->nom,
            'description'      => $r->description,
            'est_proprietaire' => $r->est_proprietaire,
            'actif'            => $r->actif,
            'ordre'            => $r->ordre,
            'champs'           => $r->relationLoaded('tousLesChamps')
                ? $r->tousLesChamps->map(fn ($c) => $this->formatChamp($c))->values()
                : [],
            'documents'        => $r->relationLoaded('typesDocument')
                ? $r->typesDocument->map(fn ($d) => [
                    'id'          => $d->id,
                    'slug'        => $d->slug,
                    'nom'         => $d->nom,
                    'obligatoire' => (bool) $d->pivot->obligatoire,
                ])->values()
                : [],
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // CHAMPS DÉPOSANT (sous-ressource d'un rôle)
    // POST   /admin/config-formulaire/roles/{id}/champs
    // PUT    /admin/config-formulaire/roles/{id}/champs/{cid}
    // PATCH  /admin/config-formulaire/roles/{id}/champs/{cid}/toggle
    // DELETE /admin/config-formulaire/roles/{id}/champs/{cid}
    // ═════════════════════════════════════════════════════════════════════════

    public function storeChamp(Request $request, string $roleId): JsonResponse
    {
        $role = ConfigRoleDeposant::findOrFail($roleId);
        $request->validate([
            'nom_champ'     => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', Rule::unique('config_champs_deposant')->where('role_id', $roleId)],
            'label'         => 'required|string|max:150',
            'placeholder'   => 'nullable|string|max:255',
            'type_champ'    => ['required', Rule::in(ConfigChampDeposant::TYPES)],
            'options_enum'  => 'nullable|array|required_if:type_champ,enum',
            'options_enum.*'=> 'string|max:100',
            'obligatoire'   => 'nullable|boolean',
            'ordre'         => 'nullable|integer|min:0',
        ]);

        $ordreMax = ConfigChampDeposant::where('role_id', $roleId)->max('ordre') ?? 0;
        $champ = ConfigChampDeposant::create([
            'role_id'      => $roleId,
            'nom_champ'    => $request->nom_champ,
            'label'        => $request->label,
            'placeholder'  => $request->placeholder,
            'type_champ'   => $request->type_champ,
            'options_enum' => $request->input('options_enum'),
            'obligatoire'  => $request->boolean('obligatoire', true),
            'actif'        => true,
            'ordre'        => $request->input('ordre', $ordreMax + 1),
        ]);

        return response()->json(['success' => true, 'message' => 'Champ ajouté.', 'data' => $this->formatChamp($champ)], 201);
    }

    public function updateChamp(Request $request, string $roleId, string $champId): JsonResponse
    {
        $champ = ConfigChampDeposant::where('role_id', $roleId)->findOrFail($champId);
        $request->validate([
            'label'         => 'sometimes|string|max:150',
            'placeholder'   => 'nullable|string|max:255',
            'options_enum'  => 'nullable|array',
            'options_enum.*'=> 'string|max:100',
            'obligatoire'   => 'sometimes|boolean',
            'ordre'         => 'sometimes|integer|min:0',
        ]);
        $champ->update($request->only(['label', 'placeholder', 'options_enum', 'obligatoire', 'ordre']));
        return response()->json(['success' => true, 'message' => 'Champ mis à jour.', 'data' => $this->formatChamp($champ->fresh())]);
    }

    public function toggleChamp(string $roleId, string $champId): JsonResponse
    {
        $champ = ConfigChampDeposant::where('role_id', $roleId)->findOrFail($champId);
        $champ->update(['actif' => !$champ->actif]);
        return response()->json(['success' => true, 'message' => $champ->actif ? 'Activé.' : 'Désactivé.', 'data' => $this->formatChamp($champ)]);
    }

    public function destroyChamp(string $roleId, string $champId): JsonResponse
    {
        ConfigChampDeposant::where('role_id', $roleId)->findOrFail($champId)->delete();
        return response()->json(['success' => true, 'message' => 'Champ supprimé.']);
    }

    private function formatChamp(ConfigChampDeposant $c): array
    {
        return [
            'id'           => $c->id,
            'nom_champ'    => $c->nom_champ,
            'label'        => $c->label,
            'placeholder'  => $c->placeholder,
            'type_champ'   => $c->type_champ,
            'options_enum' => $c->options_enum,
            'obligatoire'  => $c->obligatoire,
            'actif'        => $c->actif,
            'ordre'        => $c->ordre,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DOCUMENTS PAR RÔLE (sous-ressource d'un rôle)
    // POST   /admin/config-formulaire/roles/{id}/documents
    // PATCH  /admin/config-formulaire/roles/{id}/documents/{docId}
    // DELETE /admin/config-formulaire/roles/{id}/documents/{docId}
    // ═════════════════════════════════════════════════════════════════════════

    public function storeDocRole(Request $request, string $roleId): JsonResponse
    {
        ConfigRoleDeposant::findOrFail($roleId);
        $request->validate([
            'type_document_id' => ['required', 'uuid', 'exists:config_types_document,id',
                Rule::unique('config_docs_par_role')->where(fn ($q) => $q->where('role_id', $roleId))],
            'obligatoire'      => 'nullable|boolean',
        ]);

        $doc = ConfigDocParRole::create([
            'role_id'          => $roleId,
            'type_document_id' => $request->type_document_id,
            'obligatoire'      => $request->boolean('obligatoire', true),
        ]);

        return response()->json(['success' => true, 'message' => 'Document ajouté au rôle.', 'data' => [
            'id'               => $doc->id,
            'type_document_id' => $doc->type_document_id,
            'obligatoire'      => $doc->obligatoire,
        ]], 201);
    }

    public function updateDocRole(Request $request, string $roleId, string $docId): JsonResponse
    {
        $doc = ConfigDocParRole::where('role_id', $roleId)->findOrFail($docId);
        $request->validate(['obligatoire' => 'required|boolean']);
        $doc->update(['obligatoire' => $request->boolean('obligatoire')]);
        return response()->json(['success' => true, 'message' => 'Mis à jour.']);
    }

    public function destroyDocRole(string $roleId, string $docId): JsonResponse
    {
        ConfigDocParRole::where('role_id', $roleId)->findOrFail($docId)->delete();
        return response()->json(['success' => true, 'message' => 'Document retiré du rôle.']);
    }
}
