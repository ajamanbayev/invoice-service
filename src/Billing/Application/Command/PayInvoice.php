<?php

declare(strict_types=1);

namespace App\Billing\Application\Command;

final readonly class PayInvoice
{
    public function __construct(public string $invoiceId)
    {
    }
}
