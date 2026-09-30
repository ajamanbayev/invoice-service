<?php

declare(strict_types=1);

namespace App\Billing\Domain\Event;

final readonly class InvoiceIssued implements BillingEvent
{
    public function __construct(
        private string $id,
        public string $customerId,
        public int $grossMinor,
        public string $currency,
        public \DateTimeImmutable $issuedAt,
    ) {
    }

    public function invoiceId(): string
    {
        return $this->id;
    }
}
