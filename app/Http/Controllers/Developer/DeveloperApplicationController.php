<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Domain\Developer\Services\DeveloperApplicationService;
use App\Domain\Developer\Services\WebhookEndpointService;
use App\Http\Requests\Developer\StoreDeveloperApplicationRequest;
use App\Http\Requests\Developer\StoreWebhookEndpointRequest;
use App\Models\DeveloperApplication;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class DeveloperApplicationController
{
    public function __construct(
        private DeveloperApplicationService $applications,
        private WebhookEndpointService $webhooks,
    ) {}

    public function index(Request $request): View
    {
        $applications = DeveloperApplication::query()
            ->where('user_id', $request->user()->getKey())
            ->withCount(['orders', 'webhookEndpoints'])
            ->latest('id')
            ->get();

        return view('developer.applications.index', compact('applications'));
    }

    public function store(StoreDeveloperApplicationRequest $request): RedirectResponse
    {
        $created = $this->applications->create(
            user: $request->user(),
            name: $request->validated('name'),
            notifyUrls: $this->parseUrls($request->validated('allowed_notify_urls')),
            returnUrls: $this->parseUrls($request->validated('allowed_return_urls')),
        );

        return redirect()
            ->route('developer.applications.show', $created->application)
            ->with('issued_secret', [
                'key_id' => $created->issuedCredential->credential->key_id,
                'secret' => $created->issuedCredential->secret,
            ]);
    }

    public function show(Request $request, DeveloperApplication $application): View
    {
        $this->authorizeOwner($request, $application);
        $application->loadCount('orders')->load([
            'credentials' => static fn ($query) => $query->latest('id'),
            'webhookEndpoints' => static fn ($query) => $query->latest('id'),
        ]);
        $application->setRelation(
            'orders',
            $application->orders()->latest('id')->limit(20)->get(),
        );
        $deliveries = WebhookDelivery::query()
            ->whereHas('paymentEvent', static fn ($query) => $query->where('application_id', $application->getKey()))
            ->with('paymentEvent:id,event_id,event_type,order_id')
            ->latest('id')
            ->limit(30)
            ->get();

        $webhookEvents = WebhookEndpointService::EVENTS;

        return view('developer.applications.show', compact('application', 'deliveries', 'webhookEvents'));
    }

    public function rotate(Request $request, DeveloperApplication $application): RedirectResponse
    {
        $this->authorizeOwner($request, $application);
        $issued = $this->applications->rotateCredential($application);

        return back()->with('issued_secret', [
            'key_id' => $issued->credential->key_id,
            'secret' => $issued->secret,
        ]);
    }

    public function storeWebhook(
        StoreWebhookEndpointRequest $request,
        DeveloperApplication $application,
    ): RedirectResponse {
        $this->authorizeOwner($request, $application);

        try {
            $created = $this->webhooks->create(
                $application,
                $request->validated('name'),
                $request->validated('url'),
                $request->validated('subscribed_events'),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['url' => $exception->getMessage()]);
        }

        return back()->with('issued_webhook_secret', $created->secret);
    }

    public function disableWebhook(Request $request, DeveloperApplication $application, int $endpointId): RedirectResponse
    {
        $this->authorizeOwner($request, $application);
        abort_unless($this->webhooks->disable($application, $endpointId), 404);

        return back()->with('status', 'Webhook 端点已停用。');
    }

    public function retryDelivery(Request $request, DeveloperApplication $application, int $deliveryId): RedirectResponse
    {
        $this->authorizeOwner($request, $application);
        $delivery = WebhookDelivery::query()
            ->whereKey($deliveryId)
            ->whereHas('paymentEvent', static fn ($query) => $query->where('application_id', $application->getKey()))
            ->firstOrFail();

        DB::transaction(function () use ($delivery): void {
            $locked = WebhookDelivery::query()->lockForUpdate()->findOrFail($delivery->getKey());

            abort_unless(in_array($locked->status, ['failed', 'retrying'], true), 409);

            $locked->status = 'retrying';
            $locked->next_attempt_at = now();
            $locked->last_error = null;
            $locked->save();
        }, 3);

        return back()->with('status', 'Webhook 已加入重试队列。');
    }

    private function authorizeOwner(Request $request, DeveloperApplication $application): void
    {
        abort_unless($application->user_id === $request->user()->getKey(), 404);
    }

    private function parseUrls(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $urls = array_values(array_unique(array_filter(array_map(
            'trim',
            preg_split('/[\r\n,]+/', $raw) ?: [],
        ))));

        foreach ($urls as $url) {
            $parts = parse_url($url);

            if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
                throw ValidationException::withMessages([
                    'allowed_notify_urls' => '白名单 URL 必须是完整的 HTTPS 地址。',
                ]);
            }
        }

        return $urls;
    }
}
