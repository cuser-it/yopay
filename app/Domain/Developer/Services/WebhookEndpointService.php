<?php

declare(strict_types=1);

namespace App\Domain\Developer\Services;

use App\Domain\Developer\Data\CreatedWebhookEndpoint;
use App\Domain\Developer\Support\DeveloperUrlPolicy;
use App\Models\DeveloperApplication;
use App\Models\WebhookEndpoint;
use InvalidArgumentException;

final readonly class WebhookEndpointService
{
    public const EVENTS = [
        'payment.paid',
        'payment.amount_mismatch',
        'payment.paid_after_cancel',
        'payment.expired',
        'payment.cancelled',
        'payment.refunded',
    ];

    public function __construct(private DeveloperUrlPolicy $urlPolicy) {}

    public function create(DeveloperApplication $application, string $name, string $url, array $events): CreatedWebhookEndpoint
    {
        $this->urlPolicy->assertNotifyUrlAllowed($application, $url);
        $normalizedEvents = array_values(array_unique($events));

        if ($normalizedEvents === [] || array_diff($normalizedEvents, self::EVENTS) !== []) {
            throw new InvalidArgumentException('Webhook event subscriptions are invalid.');
        }

        $urlHash = hash('sha256', $this->urlPolicy->canonicalize($url));

        if (WebhookEndpoint::query()->where('application_id', $application->getKey())->where('url_hash', $urlHash)->exists()) {
            throw new InvalidArgumentException('This webhook URL is already configured for the application.');
        }

        $secret = 'whsec_'.$this->randomSecret();
        $endpoint = WebhookEndpoint::query()->create([
            'application_id' => $application->getKey(),
            'name' => $name,
            'url' => $url,
            'url_hash' => $urlHash,
            'secret_ciphertext' => $secret,
            'subscribed_events' => $normalizedEvents,
            'enabled' => true,
        ]);

        return new CreatedWebhookEndpoint($endpoint, $secret);
    }

    public function disable(DeveloperApplication $application, int $endpointId): bool
    {
        return WebhookEndpoint::query()
            ->where('application_id', $application->getKey())
            ->whereKey($endpointId)
            ->update(['enabled' => false]) === 1;
    }

    private function randomSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
