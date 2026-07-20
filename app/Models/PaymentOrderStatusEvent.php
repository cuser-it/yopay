<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Payment\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentOrderStatusEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'source',
        'context',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => PaymentStatus::class,
            'to_status' => PaymentStatus::class,
            'context' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'order_id');
    }
}
