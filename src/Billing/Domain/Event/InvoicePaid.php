<?php

declare(strict_types=1);

namespace App\Billing\Domain\Event;

final readonly class InvoicePaid implements BillingEvent
{
    public function __construct(
        private string $id,
        public int $grossMinor,
        public string $currency,
        public \DateTimeImmutable $paidAt,
    ) {
    }

    public function invoiceId(): string
    {
        return $this->id;
    }
}
