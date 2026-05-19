<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $table      = 'reports';
    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id',
        'organization_id',
        'report_type',
        'status',
        'period_from',
        'period_to',
        'title',
        'file_path',
        'ai_summary',
        'error_message',
        'generated_by',
        'generated_at',
    ];

    protected $casts = [
        'period_from'  => 'datetime',
        'period_to'    => 'datetime',
        'generated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
}
