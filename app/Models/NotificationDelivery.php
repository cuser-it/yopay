<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationDelivery extends Model
{
    protected $fillable = [
        'order_id',
        'payment_event_id',
        'channel',
        'destination_hash',
        'destination_ciphertext',
        'status',
        'attempt_count',
        'next_attempt_at',
        'last_attempt_at',
        'delivered_at',
        'response_summary',
        'last_error',
    ];

    protected $hidden = ['destination_ciphertext'];

    protected function casts(): array
    {
        return [
            'destination_ciphertext' => 'encrypted',
            'attempt_count' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'order_id');
    }

    public function paymentEvent(): BelongsTo
    {
        return $this->belongsTo(PaymentEvent::class, 'payment_event_id');
    }
}
