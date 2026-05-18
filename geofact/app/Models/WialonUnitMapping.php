<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WialonUnitMapping extends Model
{
    protected $table = 'wialon_unit_mappings';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    public $timestamps = true;

    protected $fillable = [
        'id',
        'organization_id',
        'wialon_unit_id',
        'wialon_unit_name',
        'ikoma_vehicle_id',
        'ikoma_connector_id',
        'last_message_ts',
        'status',
    ];

    protected $casts = [
        'wialon_unit_id'  => 'integer',
        'last_message_ts' => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'ikoma_vehicle_id');
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class, 'ikoma_connector_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
