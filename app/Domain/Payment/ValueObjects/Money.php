<?php

declare(strict_types=1);

namespace App\Domain\Payment\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $cents,
        public string $currency = 'CNY',
    ) {
        if ($this->cents < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }

        if (! preg_match('/^[A-Z]{3}$/', $this->currency)) {
            throw new InvalidArgumentException('Currency must be a three-letter uppercase code.');
        }
    }

    public static function fromDecimalString(string $amount, string $currency = 'CNY'): self
    {
        $normalized = trim($amount);

        if (! preg_match('/^(0|[1-9]\d*)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException('Amount must be a non-negative decimal with at most two fraction digits.');
        }

        $whole = (int) $matches[1];
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        if ($whole > intdiv(PHP_INT_MAX - (int) $fraction, 100)) {
            throw new InvalidArgumentException('Amount is too large.');
        }

        return new self(($whole * 100) + (int) $fraction, $currency);
    }

    public function differenceFrom(self $expected): int
    {
        $this->assertSameCurrency($expected);

        return $this->cents - $expected->cents;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->cents === $other->cents;
    }

    public function format(): string
    {
        return intdiv($this->cents, 100).'.'.str_pad((string) ($this->cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Money currencies must match.');
        }
    }
}
