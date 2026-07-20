<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentIdempotencyKey extends Model
{
    protected $fillable = [
        'scope_key',
        'idempotency_key_hash',
        'request_fingerprint',
        'order_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'order_id');
    }
}
