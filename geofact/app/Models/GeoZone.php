<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoZone extends Model
{
    protected $table = 'geozones';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'organization_id',
        'fleet_id',
        'name',
        'zone_type',
        'geometry',
        'max_stay_minutes',
        'active_from',
        'active_to',
        'version',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'geometry'   => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Contrat DC-08 : toute modification de geometry sur une zone active incrémente version
        static::updating(function (GeoZone $zone) {
            if ($zone->isDirty('geometry') && $zone->getOriginal('status') === 'active') {
                $zone->version = $zone->getOriginal('version') + 1;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }
}
