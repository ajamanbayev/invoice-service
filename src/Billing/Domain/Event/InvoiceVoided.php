<?php

declare(strict_types=1);

namespace App\Billing\Domain\Event;

final readonly class InvoiceVoided implements BillingEvent
{
    public function __construct(
        private string $id,
        public string $reason,
        public \DateTimeImmutable $voidedAt,
    ) {
    }

    public function invoiceId(): string
    {
        return $this->id;
    }
}
