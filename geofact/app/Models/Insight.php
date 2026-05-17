<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Insight extends Model
{
    protected $table = 'insights';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'organization_id',
        'scope_type',
        'scope_id',
        'insight_type',
        'language',
        'insight_text',
        'confidence_level',
        'source_kpis',
        'source_events',
        'version',
        'generated_at',
    ];

    protected $casts = [
        'source_kpis'   => 'array',
        'source_events' => 'array',
        'generated_at'  => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
