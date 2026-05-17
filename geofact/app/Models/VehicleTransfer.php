<?php

namespace App\Models;

use App\Exceptions\ContractViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleTransfer extends Model
{
    protected $table = 'vehicle_transfers';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'vehicle_id',
        'transfer_type',
        'source_organization_id',
        'source_fleet_id',
        'target_organization_id',
        'target_fleet_id',
        'historical_data_policy',
        'effective_date',
        'status',
        'initiated_by',
        'initiated_at',
        'validated_by',
        'validated_at',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'datetime',
        'initiated_at'   => 'datetime',
        'validated_at'   => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Contrat DC-16 : historical_data_policy immuable après status = validated
        static::updating(function (VehicleTransfer $transfer) {
            if ($transfer->isDirty('historical_data_policy')) {
                $original = $transfer->getOriginal('status');
                if (in_array($original, ['validated', 'completed'])) {
                    throw new ContractViolationException(
                        'DC-16',
                        'historical_data_policy ne peut plus être modifié après validation.'
                    );
                }
            }
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function sourceOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'source_organization_id');
    }

    public function targetOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'target_organization_id');
    }
}
