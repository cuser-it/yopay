<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'event_id',
        'order_id',
        'application_id',
        'event_type',
        'payload',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'order_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DeveloperApplication::class, 'application_id');
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'payment_event_id');
    }
}
