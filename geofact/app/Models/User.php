<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, FilamentUser, HasName, HasTenants
{
    protected $table = 'users';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'organization_id',
        'first_name',
        'last_name',
        'email',
        'password_hash',
        'role',
        'fleet_ids',
        'token_version',
        'status',
        'last_login_at',
        'created_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // password_hash jamais exposé (DC-14)
    protected $hidden = ['password_hash'];

    protected $casts = [
        'fleet_ids'     => 'array',
        'last_login_at' => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    // JWTSubject : identifiant du sujet dans le token
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    // JWTSubject : claims personnalisés du JWT GEOFACT (C-12.2)
    public function getJWTCustomClaims(): array
    {
        return [
            'actor_type'      => 'human',
            'organization_id' => $this->organization_id,
            'fleet_ids'       => $this->fleet_ids ?? [],
            'role'            => $this->role,
            'scope_type'      => in_array($this->role, ['fleet_admin', 'supervisor']) ? 'fleet' : 'organization',
            'token_version'   => $this->token_version,
        ];
    }

    // FilamentUser: contrôle l'accès aux panels selon le rôle
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'superadmin') {
            return $this->role === 'geofact_admin' && $this->status === 'active';
        }
        return in_array($this->role, ['org_admin', 'fleet_admin', 'supervisor', 'integrator', 'driver'], true)
            && $this->status === 'active';
    }

    // HasTenants: retourne les organisations accessibles à cet utilisateur
    public function getTenants(Panel $panel): Collection
    {
        return Organization::where('id', $this->organization_id)
            ->where('status', 'active')
            ->get();
    }

    // HasTenants: vérifie si cet utilisateur peut accéder à l'organisation donnée
    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant->id === $this->organization_id;
    }

    // Override Authenticatable — notre champ est password_hash pas password
    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }

    // Filament v4 : nom affiché dans le panel (pas de colonne 'name' — DC-14)
    public function getFilamentName(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: $this->email;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
