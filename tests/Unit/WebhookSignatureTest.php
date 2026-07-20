<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Delivery\Support\WebhookSignature;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase
{
    public function test_signature_binds_timestamp_and_raw_body(): void
    {
        $timestamp = '1784361600';
        $body = '{"event_id":"01TEST","data":{"paid_amount_cents":1000}}';
        $signature = WebhookSignature::sign($timestamp, $body, 'whsec_test');

        self::assertTrue(WebhookSignature::verify($timestamp, $body, 'whsec_test', $signature));
        self::assertTrue(WebhookSignature::verify($timestamp, $body, 'whsec_test', 'sha256='.$signature));
        self::assertFalse(WebhookSignature::verify($timestamp, $body.' ', 'whsec_test', $signature));
        self::assertFalse(WebhookSignature::verify('1784361601', $body, 'whsec_test', $signature));
    }
}
