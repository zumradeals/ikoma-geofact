<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryPolicy extends Model
{
    protected $table = 'delivery_policies';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id',
        'organization_id',
        'event_type_filter',
        'min_severity',
        'channels',
        'recipient_email',
        'recipient_phone',
        'recipient_webhook_url',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'channels'   => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
