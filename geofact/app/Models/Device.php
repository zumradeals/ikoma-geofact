<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $table = 'devices';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'vehicle_id',
        'organization_id',
        'imei',
        'provider_id',
        'serial_number',
        'firmware_version',
        'status',
        'last_seen_at',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function telemetryEvents(): HasMany
    {
        return $this->hasMany(TelemetryEvent::class);
    }
}
