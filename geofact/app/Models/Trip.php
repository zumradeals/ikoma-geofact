<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    protected $table = 'trips';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'vehicle_id',
        'driver_id',
        'organization_id',
        'fleet_id',
        'status',
        'started_at',
        'ended_at',
        'duration_minutes',
        'distance_km',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'anomaly_note',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'started_at'      => 'datetime',
        'ended_at'        => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
        'start_latitude'  => 'decimal:7',
        'start_longitude' => 'decimal:7',
        'end_latitude'    => 'decimal:7',
        'end_longitude'   => 'decimal:7',
        'distance_km'     => 'decimal:3',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function telemetryEvents(): HasMany
    {
        return $this->hasMany(TelemetryEvent::class);
    }
}
