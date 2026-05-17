<?php

namespace App\Models;

use App\Exceptions\ContractViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $table = 'alerts';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    // alerts n'a que created_at (DC-09)
    public $timestamps = false;

    protected $fillable = [
        'id',
        'organization_id',
        'fleet_id',
        'vehicle_id',
        'driver_id',
        'trip_id',
        'rule_id',
        'rule_type',
        'event_type',
        'severity',
        'status',
        'triggered_at',
        'acknowledged_at',
        'resolved_at',
        'resolution_note',
        'escalated_at',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'triggered_at'    => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at'     => 'datetime',
        'escalated_at'    => 'datetime',
        'created_at'      => 'datetime',
        'payload'         => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Contrat DC-09 : suppression physique interdite — archivage uniquement
        static::deleting(function () {
            throw new ContractViolationException('DC-09', 'alerts : suppression physique interdite — archivage uniquement.');
        });

        // Contrat DC-09 : resolution_note obligatoire si status = resolved
        static::updating(function (Alert $alert) {
            if ($alert->isDirty('status') && $alert->status === 'resolved') {
                if (empty($alert->resolution_note)) {
                    throw new ContractViolationException('DC-09', 'resolution_note est obligatoire quand status = resolved.');
                }
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }
}
