<?php

declare(strict_types=1);

$notificationTargets = json_decode((string) env('PAYMENT_ADMIN_NOTIFICATION_TARGETS', '[]'), true);

return [
    'currency' => 'CNY',
    'public_minimum_amount_cents' => (int) env('PAYMENT_PUBLIC_MINIMUM_AMOUNT_CENTS', 1),
    'public_maximum_amount_cents' => (int) env('PAYMENT_PUBLIC_MAXIMUM_AMOUNT_CENTS', 100000000),
    'order_expiration_minutes' => (int) env('PAYMENT_ORDER_EXPIRATION_MINUTES', 15),
    'checkout_token_ttl_minutes' => (int) env('PAYMENT_CHECKOUT_TOKEN_TTL_MINUTES', 120),
    'callback_processing_timeout_seconds' => (int) env('PAYMENT_CALLBACK_PROCESSING_TIMEOUT_SECONDS', 120),
    'notifications' => [
        'targets' => is_array($notificationTargets) ? $notificationTargets : [],
    ],
    'developer_api' => [
        'timestamp_tolerance_seconds' => (int) env('DEVELOPER_API_TIMESTAMP_TOLERANCE_SECONDS', 300),
        'nonce_ttl_seconds' => (int) env('DEVELOPER_API_NONCE_TTL_SECONDS', 600),
    ],
    'webhooks' => [
        'retry_delays_seconds' => [60, 300, 1800, 7200, 43200, 86400],
        'timeout_seconds' => 10,
        'processing_timeout_seconds' => (int) env('PAYMENT_DELIVERY_PROCESSING_TIMEOUT_SECONDS', 120),
    ],
    'gateway' => [
        'default' => env('PAYMENT_GATEWAY_VERSION', 'v2'),
        'creation_lease_seconds' => (int) env('PAYMENT_GATEWAY_CREATION_LEASE_SECONDS', 30),
        'easypay' => [
            'merchant_id' => env('EASYPAY_MERCHANT_ID'),
            'v1' => [
                'base_url' => env('EASYPAY_V1_BASE_URL', 'https://pay.yunvix.com'),
                'merchant_key' => env('EASYPAY_V1_MERCHANT_KEY'),
            ],
            'v2' => [
                'base_url' => env('EASYPAY_V2_BASE_URL', 'https://pay.yunvix.com'),
                'merchant_private_key' => env('EASYPAY_V2_MERCHANT_PRIVATE_KEY'),
                'platform_public_key' => env('EASYPAY_V2_PLATFORM_PUBLIC_KEY'),
                'timestamp_tolerance_seconds' => (int) env('EASYPAY_V2_TIMESTAMP_TOLERANCE_SECONDS', 300),
            ],
        ],
    ],
];
