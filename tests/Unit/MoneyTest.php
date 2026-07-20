<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Payment\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_parses_and_formats_integer_cents_without_floating_point(): void
    {
        $money = Money::fromDecimalString('100.05');

        self::assertSame(10005, $money->cents);
        self::assertSame('100.05', $money->format());
    }

    public function test_it_rejects_more_than_two_fraction_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimalString('1.001');
    }

    public function test_it_calculates_signed_differences(): void
    {
        self::assertSame(-1, (new Money(999))->differenceFrom(new Money(1000)));
        self::assertSame(1, (new Money(1001))->differenceFrom(new Money(1000)));
    }
}
