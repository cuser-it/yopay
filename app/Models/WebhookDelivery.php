<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WebhookDelivery extends Model
{
    protected $fillable = [
        'payment_event_id',
        'webhook_endpoint_id',
        'destination_hash',
        'destination_url',
        'secret_ciphertext',
        'status',
        'attempt_count',
        'next_attempt_at',
        'last_attempt_at',
        'delivered_at',
        'response_status',
        'response_summary',
        'last_error',
    ];

    protected $hidden = ['secret_ciphertext'];

    protected function casts(): array
    {
        return [
            'secret_ciphertext' => 'encrypted',
            'attempt_count' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'response_status' => 'integer',
        ];
    }

    public function paymentEvent(): BelongsTo
    {
        return $this->belongsTo(PaymentEvent::class, 'payment_event_id');
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(WebhookDeliveryAttempt::class, 'webhook_delivery_id');
    }
}
