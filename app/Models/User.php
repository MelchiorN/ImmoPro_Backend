<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasUuids, HasApiTokens, LogsActivity;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'telephone',
        'country',
        'city',
        'profile_picture',
        'role',
        'status',
        'password',
        'provider',
        'provider_id',
        'provider_token',
        'device_token',
        'essais_gratuits_restants',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ─── Spatie ActivityLog ───────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([])
            ->dontSubmitEmptyLogs();
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function preference(): HasOne
    {
        return $this->hasOne(Preference::class);
    }

    public function pieceIdentite(): HasOne
    {
        return $this->hasOne(PieceIdentite::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function historiqueConnexions(): HasMany
    {
        return $this->hasMany(HistoriqueConnexion::class);
    }

    // ─── Biens immobiliers ────────────────────────────────────────────────────

    public function biens(): HasMany
    {
        return $this->hasMany(\App\Models\Bien::class);
    }

    public function biensAgentAssigne(): HasMany
    {
        return $this->hasMany(\App\Models\Bien::class, 'agent_id');
    }

    public function favoris(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Bien::class, 'favoris', 'user_id', 'bien_id')
                    ->withTimestamps();
    }

    // ─── Abonnements ──────────────────────────────────────────────────────────

    public function abonnements(): HasMany
    {
        return $this->hasMany(UserAbonnement::class);
    }

    /**
     * Retourne l'abonnement actif ayant encore des publications disponibles.
     * Prend le plus ancien actif en premier (FIFO).
     */
    public function abonnementActif(): ?\App\Models\UserAbonnement
    {
        return $this->abonnements()
            ->where('statut', 'actif')
            ->where('nb_publications_restantes', '>', 0)
            ->oldest('date_achat')
            ->first();
    }

    /**
     * Vérifie si l'utilisateur peut encore soumettre un bien gratuitement ou via abonnement.
     */
    public function peutPublier(): bool
    {
        return $this->essais_gratuits_restants > 0 || $this->abonnementActif() !== null;
    }
}
