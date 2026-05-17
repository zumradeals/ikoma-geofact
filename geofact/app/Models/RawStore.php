<?php

namespace App\Models;

use App\Exceptions\ContractViolationException;
use Illuminate\Database\Eloquent\Model;

class RawStore extends Model
{
    protected $table = 'raw_store';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    // Table immuable — pas de updated_at (DC-11)
    public $timestamps = false;

    protected $fillable = [
        'id',
        'connector_id',
        'organization_id',
        'received_at',
        'payload_raw',
        'payload_format',
        'flag',
        'canonical_ref',
        'processed_at',
    ];

    protected $casts = [
        'received_at'  => 'datetime',
        'processed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Contrat DC-11 : INSERT uniquement — aucun UPDATE ni DELETE jamais
        static::updating(function () {
            throw new ContractViolationException('DC-11', 'raw_store est immuable — aucun UPDATE autorisé.');
        });

        static::deleting(function () {
            throw new ContractViolationException('DC-11', 'raw_store est immuable — aucun DELETE autorisé.');
        });
    }
}
