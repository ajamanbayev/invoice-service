<?php

declare(strict_types=1);

namespace App\Billing\Application;

use App\Billing\Application\View\InvoiceView;
use App\Billing\Domain\Model\InvoiceId;
use App\Billing\Domain\Model\InvoiceRepository;

final readonly class GetInvoiceHandler
{
    public function __construct(private InvoiceRepository $invoices)
    {
    }

    public function handle(string $invoiceId): InvoiceView
    {
        return InvoiceView::fromInvoice($this->invoices->get(InvoiceId::fromString($invoiceId)));
    }
}
