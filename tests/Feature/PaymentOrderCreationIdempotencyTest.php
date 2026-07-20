<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Payment\Data\CreatePaymentOrderData;
use App\Domain\Payment\Enums\OrderSource;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Exceptions\IdempotencyConflictException;
use App\Domain\Payment\Services\PaymentOrderCreationService;
use App\Domain\Payment\ValueObjects\Money;
use App\Models\DeveloperApplication;
use App\Models\PaymentIdempotencyKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PaymentOrderCreationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_order_retry_uses_original_fingerprint_after_payer_selects_method(): void
    {
        $application = $this->application();
        $data = $this->data($application, 1000);
        $orders = app(PaymentOrderCreationService::class);
        $first = $orders->create($data, 'idempotency-key-one');
        $first->order->forceFill(['payment_method' => PaymentMethod::Alipay])->save();

        $replayed = $orders->create($data, 'idempotency-key-two');

        self::assertTrue($replayed->replayed);
        self::assertSame($first->order->getKey(), $replayed->order->getKey());
        self::assertSame(2, PaymentIdempotencyKey::query()->count());
    }

    public function test_reusing_a_key_with_different_amount_is_rejected(): void
    {
        $application = $this->application();
        $orders = app(PaymentOrderCreationService::class);
        $orders->create($this->data($application, 1000), 'same-key');

        $this->expectException(IdempotencyConflictException::class);

        $orders->create($this->data($application, 1001), 'same-key');
    }

    private function application(): DeveloperApplication
    {
        return DeveloperApplication::query()->create([
            'public_id' => (string) Str::ulid(),
            'user_id' => User::factory()->create()->getKey(),
            'name' => 'Test application',
            'status' => 'active',
        ]);
    }

    private function data(DeveloperApplication $application, int $amountCents): CreatePaymentOrderData
    {
        return new CreatePaymentOrderData(
            source: OrderSource::DeveloperApi,
            amount: new Money($amountCents),
            subject: 'Fixed order',
            paymentMethod: null,
            clientIp: '203.0.113.10',
            application: $application,
            externalOrderNumber: 'external-order-1',
        );
    }
}
