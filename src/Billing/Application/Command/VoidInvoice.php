<?php

declare(strict_types=1);

namespace App\Billing\Application\Command;

final readonly class VoidInvoice
{
    public function __construct(
        public string $invoiceId,
        public string $reason,
    ) {
    }
}
