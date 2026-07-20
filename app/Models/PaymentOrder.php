<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Gateway\Enums\GatewayApiVersion;
use App\Domain\Gateway\Enums\PaymentActionType;
use App\Domain\Payment\Enums\OrderSource;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentOrder extends Model
{
    protected $fillable = [
        'order_no',
        'application_id',
        'external_order_no',
        'source',
        'status',
        'version',
        'expected_amount_cents',
        'paid_amount_cents',
        'amount_difference_cents',
        'currency',
        'subject',
        'description',
        'payment_method',
        'gateway',
        'gateway_api_version',
        'gateway_order_no',
        'gateway_trade_no',
        'checkout_token_hash',
        'checkout_token_ciphertext',
        'payment_action_type',
        'payment_action_payload',
        'payment_direct_url',
        'notify_url',
        'notify_secret_ciphertext',
        'return_url',
        'client_ip',
        'gateway_create_attempt_count',
        'gateway_create_last_attempt_at',
        'gateway_last_error',
        'metadata',
        'status_changed_at',
        'expires_at',
        'checkout_token_expires_at',
        'cancelled_at',
        'paid_at',
        'failed_at',
        'last_reconciled_at',
    ];

    protected $hidden = ['checkout_token_hash', 'checkout_token_ciphertext', 'notify_secret_ciphertext'];

    protected function casts(): array
    {
        return [
            'source' => OrderSource::class,
            'status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'gateway_api_version' => GatewayApiVersion::class,
            'payment_action_type' => PaymentActionType::class,
            'expected_amount_cents' => 'integer',
            'paid_amount_cents' => 'integer',
            'amount_difference_cents' => 'integer',
            'version' => 'integer',
            'checkout_token_ciphertext' => 'encrypted',
            'notify_secret_ciphertext' => 'encrypted',
            'gateway_create_attempt_count' => 'integer',
            'gateway_create_last_attempt_at' => 'immutable_datetime',
            'metadata' => 'array',
            'status_changed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'checkout_token_expires_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'last_reconciled_at' => 'immutable_datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DeveloperApplication::class, 'application_id');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(PaymentOrderStatusEvent::class, 'order_id');
    }

    public function callbacks(): HasMany
    {
        return $this->hasMany(PaymentCallback::class, 'order_id');
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class, 'order_id');
    }

    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'order_id');
    }
}
