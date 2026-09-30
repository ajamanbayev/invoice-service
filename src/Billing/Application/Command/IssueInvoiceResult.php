<?php

declare(strict_types=1);

namespace App\Billing\Application\Command;

use App\Billing\Application\View\InvoiceView;

final readonly class IssueInvoiceResult
{
    public function __construct(
        public InvoiceView $invoice,
        public bool $replayed,
    ) {
    }
}
