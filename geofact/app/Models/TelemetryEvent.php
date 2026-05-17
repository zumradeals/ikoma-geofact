<?php

namespace App\Models;

use App\Exceptions\ContractViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryEvent extends Model
{
    protected $table = 'telemetry_events';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    // Table immuable — pas de updated_at (DC-10)
    public $timestamps = false;

    protected $fillable = [
        'id',
        'connector_id',
        'device_id',
        'vehicle_id',
        'organization_id',
        'trip_id',
        'event_type',
        'ts',
        'received_at',
        'latitude',
        'longitude',
        'speed_kmh',
        'heading',
        'altitude_m',
        'fuel_level_pct',
        'temperature_celsius',
        'ignition',
        'payload',
        'completeness',
        'missing_fields',
        'raw_ref',
    ];

    protected $casts = [
        'ts'            => 'datetime:Y-m-d H:i:s.v',
        'received_at'   => 'datetime:Y-m-d H:i:s.v',
        'payload'       => 'array',
        'missing_fields'=> 'array',
        'latitude'      => 'decimal:7',
        'longitude'     => 'decimal:7',
        'speed_kmh'     => 'decimal:2',
        'fuel_level_pct'=> 'decimal:2',
        'temperature_celsius' => 'decimal:2',
        'ignition'      => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Contrat DC-10 : INSERT uniquement — aucun UPDATE ni DELETE jamais
        static::updating(function () {
            throw new ContractViolationException('DC-10', 'telemetry_events est immuable — aucun UPDATE autorisé.');
        });

        static::deleting(function () {
            throw new ContractViolationException('DC-10', 'telemetry_events est immuable — aucun DELETE autorisé.');
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
