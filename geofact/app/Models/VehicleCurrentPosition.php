<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleCurrentPosition extends Model
{
    protected $table = 'vehicle_current_positions';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id',
        'organization_id',
        'vehicle_id',
        'connector_id',
        'device_id',
        'telemetry_event_id',
        'provider_id',
        'provider_unit_id',
        'latitude',
        'longitude',
        'speed_kmh',
        'heading',
        'ignition',
        'position_ts',
        'received_at',
        'freshness_status',
        'source_status',
        'raw_age_seconds',
    ];

    protected $casts = [
        'latitude'        => 'decimal:7',
        'longitude'       => 'decimal:7',
        'speed_kmh'       => 'decimal:2',
        'ignition'        => 'boolean',
        'position_ts'     => 'datetime:Y-m-d H:i:s.v',
        'received_at'     => 'datetime:Y-m-d H:i:s.v',
        'raw_age_seconds' => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function telemetryEvent(): BelongsTo
    {
        return $this->belongsTo(TelemetryEvent::class);
    }

    public function getLiveFreshnessStatusAttribute(): string
    {
        if (! $this->position_ts) {
            return 'unknown';
        }

        $ageSeconds = max(0, (int) Carbon::parse($this->position_ts)->diffInSeconds(now()));

        return match (true) {
            $ageSeconds <= 300  => 'fresh',
            $ageSeconds <= 3600 => 'delayed',
            default             => 'stale',
        };
    }
}
