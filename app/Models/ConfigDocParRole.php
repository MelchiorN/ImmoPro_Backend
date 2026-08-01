<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigDocParRole extends Model
{
    use HasUuids;

    protected $table     = 'config_docs_par_role';
    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = ['role_id', 'type_document_id', 'obligatoire'];

    protected function casts(): array
    {
        return ['obligatoire' => 'boolean'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(ConfigRoleDeposant::class, 'role_id');
    }

    public function typeDocument(): BelongsTo
    {
        return $this->belongsTo(ConfigTypeDocument::class, 'type_document_id');
    }
}
