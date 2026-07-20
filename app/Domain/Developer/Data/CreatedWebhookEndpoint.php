<?php

declare(strict_types=1);

namespace App\Domain\Developer\Data;

use App\Models\WebhookEndpoint;

final readonly class CreatedWebhookEndpoint
{
    public function __construct(
        public WebhookEndpoint $endpoint,
        public string $secret,
    ) {}
}
