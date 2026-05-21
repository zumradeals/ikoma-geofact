<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\VehicleCurrentPosition;

class Vehicle extends Model
{
    protected $table = 'vehicles';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'fleet_id',
        'organization_id',
        'name',
        'plate',
        'brand',
        'model',
        'year',
        'status',
        'created_by',
        'created_at',
        'updated_at',
        'archived_at',
        'deleted_at',
    ];

    protected $casts = [
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'archived_at' => 'datetime',
        'deleted_at'  => 'datetime',
    ];

    // Contrat DC-03 : organization_id déduit de fleet_id — jamais saisi manuellement
    public function setFleetIdAttribute(string $value): void
    {
        $this->attributes['fleet_id'] = $value;

        $fleet = Fleet::find($value);
        if ($fleet) {
            $this->attributes['organization_id'] = $fleet->organization_id;
        }
    }

    // Scope local : trip actif ou en pause pour ce véhicule (C-11)
    public function scopeActiveTrip(Builder $query): Builder
    {
        return $query->whereHas('trips', function (Builder $q) {
            $q->whereIn('status', ['active', 'paused']);
        });
    }

    public function activeTrip(): HasOne
    {
        return $this->hasOne(Trip::class)->whereIn('status', ['active', 'paused']);
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function device(): HasOne
    {
        return $this->hasOne(Device::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function telemetryEvents(): HasMany
    {
        return $this->hasMany(TelemetryEvent::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(VehicleTransfer::class);
    }

    public function currentPosition(): HasOne
    {
        return $this->hasOne(VehicleCurrentPosition::class);
    }
}
