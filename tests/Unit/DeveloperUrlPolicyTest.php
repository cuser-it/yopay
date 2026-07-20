<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Delivery\Support\PublicHttpUrlGuard;
use App\Domain\Developer\Support\DeveloperUrlPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DeveloperUrlPolicyTest extends TestCase
{
    public function test_it_preserves_case_sensitive_path_and_query_values(): void
    {
        $policy = new DeveloperUrlPolicy(new PublicHttpUrlGuard());

        self::assertSame(
            'https://example.com/Case/Sensitive?Token=AbC',
            $policy->canonicalize('HTTPS://Example.COM/Case/Sensitive/?Token=AbC'),
        );
    }

    public function test_it_rejects_path_segments_that_can_escape_an_allowlisted_prefix(): void
    {
        $policy = new DeveloperUrlPolicy(new PublicHttpUrlGuard());

        $this->expectException(InvalidArgumentException::class);

        $policy->canonicalize('https://example.com/webhooks/%2e%2e/internal');
    }

    public function test_it_rejects_encoded_path_separators(): void
    {
        $policy = new DeveloperUrlPolicy(new PublicHttpUrlGuard());

        $this->expectException(InvalidArgumentException::class);

        $policy->canonicalize('https://example.com/webhooks%2Finternal');
    }
}
