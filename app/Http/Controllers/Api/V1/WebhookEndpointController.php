<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Developer\Services\WebhookEndpointService;
use App\Http\Requests\Developer\StoreWebhookEndpointRequest;
use App\Http\Responses\ApiResponse;
use App\Models\DeveloperApplication;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final readonly class WebhookEndpointController
{
    public function __construct(private WebhookEndpointService $endpoints) {}

    public function index(Request $request): JsonResponse
    {
        $application = $this->application($request);
        $items = WebhookEndpoint::query()
            ->where('application_id', $application->getKey())
            ->latest('id')
            ->get()
            ->map(static fn (WebhookEndpoint $endpoint): array => self::transform($endpoint))
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        try {
            $created = $this->endpoints->create(
                application: $this->application($request),
                name: $request->validated('name'),
                url: $request->validated('url'),
                events: $request->validated('subscribed_events'),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('INVALID_WEBHOOK_ENDPOINT', $exception->getMessage(), 422);
        }

        $data = self::transform($created->endpoint);
        $data['signing_secret'] = $created->secret;

        return ApiResponse::success($data, 201);
    }

    public function destroy(Request $request, int $endpointId): JsonResponse
    {
        if (! $this->endpoints->disable($this->application($request), $endpointId)) {
            return ApiResponse::error('WEBHOOK_ENDPOINT_NOT_FOUND', 'Webhook 端点不存在。', 404);
        }

        return ApiResponse::success(['disabled' => true]);
    }

    private function application(Request $request): DeveloperApplication
    {
        return $request->attributes->get('developer_application');
    }

    private static function transform(WebhookEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->getKey(),
            'name' => $endpoint->name,
            'url' => $endpoint->url,
            'subscribed_events' => $endpoint->subscribed_events,
            'enabled' => $endpoint->enabled,
            'last_success_at' => $endpoint->last_success_at?->toAtomString(),
            'last_failure_at' => $endpoint->last_failure_at?->toAtomString(),
            'created_at' => $endpoint->created_at?->toAtomString(),
        ];
    }
}
