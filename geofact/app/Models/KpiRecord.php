<?php

namespace App\Models;

use App\Exceptions\ContractViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiRecord extends Model
{
    protected $table = 'kpi_records';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    // Table immuable — pas de updated_at (DC-12)
    public $timestamps = false;

    protected $fillable = [
        'id',
        'organization_id',
        'scope_type',
        'scope_id',
        'kpi_type',
        'mode',
        'period_from',
        'period_to',
        'value',
        'unit',
        'version',
        'computed_at',
        'scheduler_run_id',
    ];

    protected $casts = [
        'period_from' => 'datetime',
        'period_to'   => 'datetime',
        'computed_at' => 'datetime',
        'value'       => 'decimal:4',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        // Contrat DC-12 : INSERT uniquement — aucun UPDATE ni DELETE jamais
        static::updating(function () {
            throw new ContractViolationException('DC-12', 'kpi_records est immuable — aucun UPDATE autorisé.');
        });

        static::deleting(function () {
            throw new ContractViolationException('DC-12', 'kpi_records est immuable — aucun DELETE autorisé.');
        });
    }
}
