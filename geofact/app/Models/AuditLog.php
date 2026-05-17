<?php

namespace App\Models;

use App\Exceptions\ContractViolationException;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    // Table immuable — pas de updated_at (DC-15)
    public $timestamps = false;

    protected $fillable = [
        'id',
        'actor_id',
        'actor_role',
        'organization_id',
        'action',
        'resource_type',
        'resource_id',
        'result',
        'ip_address',
        'user_agent',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s.v',
        'payload'    => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Contrat DC-15 : INSERT uniquement — aucun UPDATE ni DELETE jamais
        static::updating(function () {
            throw new ContractViolationException('DC-15', 'audit_logs est immuable — aucun UPDATE autorisé.');
        });

        static::deleting(function () {
            throw new ContractViolationException('DC-15', 'audit_logs est immuable — aucun DELETE autorisé.');
        });
    }
}
