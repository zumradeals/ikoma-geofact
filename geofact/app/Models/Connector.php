<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Connector extends Model
{
    protected $table = 'connectors';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'organization_id',
        'provider_id',
        'connector_type',
        'token_hash',
        'token_version',
        'certified_by',
        'certified_at',
        'status',
        'contract_versions',
        'provider_config',
        'last_sync_at',
        'created_at',
        'updated_at',
    ];

    // token_hash exclu des casts — jamais exposé en clair
    protected $hidden = ['token_hash'];

    protected $casts = [
        'contract_versions' => 'array',
        'provider_config'   => 'encrypted:array',
        'certified_at'      => 'datetime',
        'last_sync_at'      => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    public function getProviderConfigValue(string $key, mixed $default = null): mixed
    {
        return ($this->provider_config ?? [])[$key] ?? $default;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
