<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
