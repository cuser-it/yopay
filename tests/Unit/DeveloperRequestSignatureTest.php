<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Developer\Support\DeveloperRequestSignature;
use PHPUnit\Framework\TestCase;

final class DeveloperRequestSignatureTest extends TestCase
{
    public function test_it_builds_the_documented_canonical_string(): void
    {
        $canonical = DeveloperRequestSignature::canonical(
            timestamp: '1784361600',
            nonce: 'nonce-1234567890',
            method: 'post',
            requestTarget: '/api/v1/payments?expand=checkout',
            rawBody: '{"amount_cents":100}',
        );

        self::assertSame(implode("\n", [
            '1784361600',
            'nonce-1234567890',
            'POST',
            '/api/v1/payments?expand=checkout',
            hash('sha256', '{"amount_cents":100}'),
        ]), $canonical);
    }

    public function test_it_verifies_prefixed_and_unprefixed_signatures(): void
    {
        $canonical = 'canonical-request';
        $signature = DeveloperRequestSignature::sign($canonical, 'secret');

        self::assertTrue(DeveloperRequestSignature::verify($canonical, 'secret', $signature));
        self::assertTrue(DeveloperRequestSignature::verify($canonical, 'secret', 'sha256='.$signature));
        self::assertFalse(DeveloperRequestSignature::verify($canonical, 'wrong-secret', $signature));
    }
}
