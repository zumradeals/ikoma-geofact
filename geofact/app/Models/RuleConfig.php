<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleConfig extends Model
{
    protected $table      = 'rule_configs';
    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id',
        'organization_id',
        'rule_id',
        'params',
        'is_enabled',
        'updated_by',
    ];

    protected $casts = [
        'params'     => 'array',
        'is_enabled' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
