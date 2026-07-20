<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WebhookDeliveryAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'webhook_delivery_id',
        'attempt_no',
        'request_timestamp',
        'response_status',
        'response_summary',
        'duration_ms',
        'error',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'request_timestamp' => 'immutable_datetime',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
            'attempted_at' => 'immutable_datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(WebhookDelivery::class, 'webhook_delivery_id');
    }
}
