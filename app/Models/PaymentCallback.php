<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Gateway\Enums\GatewayApiVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentCallback extends Model
{
    protected $fillable = [
        'request_id',
        'order_id',
        'gateway',
        'gateway_api_version',
        'fingerprint',
        'gateway_trade_no',
        'signature_valid',
        'merchant_valid',
        'processing_status',
        'outcome',
        'error_code',
        'sanitized_payload',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway_api_version' => GatewayApiVersion::class,
            'signature_valid' => 'boolean',
            'merchant_valid' => 'boolean',
            'sanitized_payload' => 'array',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'order_id');
    }
}
