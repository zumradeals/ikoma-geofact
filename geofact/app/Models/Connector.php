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
        'certified_at'      => 'datetime',
        'last_sync_at'      => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    public function getProviderConfigAttribute(?string $value): array
    {
        if (empty($value)) {
            return [];
        }
        try {
            return json_decode(decrypt($value), true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function setProviderConfigAttribute(mixed $value): void
    {
        if (empty($value)) {
            $this->attributes['provider_config'] = null;
            return;
        }
        $arr = is_array($value) ? $value : (array) $value;
        // Ne pas chiffrer les valeurs vides
        $arr = array_filter($arr, fn ($v) => $v !== null && $v !== '');
        $this->attributes['provider_config'] = empty($arr) ? null : encrypt(json_encode($arr));
    }

    public function getProviderConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->provider_config[$key] ?? $default;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
