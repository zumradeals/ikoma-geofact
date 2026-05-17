<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    protected $table = 'drivers';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'organization_id',
        'fleet_id',
        'first_name',
        'last_name',
        'phone',
        'license_number',
        'license_expiry',
        'status',
        'created_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'license_expiry' => 'date',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
