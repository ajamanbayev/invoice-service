<?php

declare(strict_types=1);

namespace App\Billing\Domain\Exception;

final class CurrencyMismatch extends BillingException
{
    public static function between(string $expected, string $actual): self
    {
        return new self(sprintf('Currency mismatch: expected %s, got %s.', $expected, $actual));
    }
}
