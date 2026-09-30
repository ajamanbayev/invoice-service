<?php

declare(strict_types=1);

namespace App\Billing\Application;

use App\Billing\Application\Command\VoidInvoice;
use App\Billing\Application\View\InvoiceView;
use App\Billing\Domain\Clock;
use App\Billing\Domain\Model\InvoiceId;
use App\Billing\Domain\Model\InvoiceRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class VoidInvoiceHandler
{
    public function __construct(
        private InvoiceRepository $invoices,
        private Clock $clock,
        private PersistInvoice $persist,
    ) {
    }

    public function __invoke(VoidInvoice $command): InvoiceView
    {
        $invoice = $this->invoices->get(InvoiceId::fromString($command->invoiceId));
        $invoice->void($command->reason, $this->clock);
        $this->persist->save($invoice);

        return InvoiceView::fromInvoice($invoice);
    }
}
