<?php

declare(strict_types=1);

namespace App\Billing\Application;

use App\Billing\Application\Event\DomainEventDispatcher;
use App\Billing\Domain\Model\Invoice;
use App\Billing\Domain\Model\InvoiceRepository;

/**
 * Save first, then publish. A failed save must not emit events.
 * The dispatcher is in-process here; an outbox would replace it without touching handlers.
 */
final readonly class PersistInvoice
{
    public function __construct(
        private InvoiceRepository $invoices,
        private DomainEventDispatcher $events,
    ) {
    }

    public function save(Invoice $invoice): void
    {
        $this->invoices->save($invoice);
        foreach ($invoice->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }
    }
}
