<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

use App\Billing\Domain\Exception\CurrencyMismatch;
use App\Billing\Domain\Tax\VatRate;

/**
 * Amounts are integer minor units (cents). The type never stores a float.
 */
final readonly class Money
{
    public function __construct(
        public int $amountMinor,
        public string $currency,
    ) {
        if ($amountMinor < 0) {
            throw new \InvalidArgumentException('Amount must be zero or positive.');
        }

        if (1 !== preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('Currency must be an ISO-4217 code.');
        }
    }

    public static function zero(string $currency): self
    {
        if (1 !== preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('Currency must be an ISO-4217 code.');
        }

        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }

        $sum = $this->amountMinor + $other->amountMinor;
        if (!is_int($sum)) {
            throw new \OverflowException('Money amount overflow.');
        }

        return new self($sum, $this->currency);
    }

    public function multiply(int $factor): self
    {
        if ($factor < 0) {
            throw new \InvalidArgumentException('Factor must be zero or positive.');
        }

        if ($factor === 0 || $this->amountMinor === 0) {
            return new self(0, $this->currency);
        }

        $product = $this->amountMinor * $factor;
        if (!is_int($product) || intdiv($product, $factor) !== $this->amountMinor) {
            throw new \OverflowException('Money amount overflow.');
        }

        return new self($product, $this->currency);
    }

    /**
     * Half-up rounding. 10_000 basis points = 100%.
     * 1 minor unit at 50% (5000 bp) becomes 1, at 4999 bp it becomes 0.
     */
    public function applyRate(VatRate $rate): self
    {
        if ($this->amountMinor === 0 || $rate->basisPoints === 0) {
            return new self(0, $this->currency);
        }

        $product = $this->amountMinor * $rate->basisPoints;
        if (!is_int($product) || intdiv($product, $rate->basisPoints) !== $this->amountMinor) {
            throw new \OverflowException('Money amount overflow.');
        }

        $half = 5_000;
        if ($product > PHP_INT_MAX - $half) {
            throw new \OverflowException('Money amount overflow.');
        }

        return new self(intdiv($product + $half, 10_000), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor
            && $this->currency === $other->currency;
    }
}
