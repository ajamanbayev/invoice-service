<?php

declare(strict_types=1);

namespace App\Billing\Application;

use App\Billing\Application\Command\IssueInvoice;
use App\Billing\Application\Command\IssueInvoiceResult;
use App\Billing\Application\Command\LineInput;
use App\Billing\Application\View\InvoiceView;
use App\Billing\Domain\Clock;
use App\Billing\Domain\Exception\ConflictingIdempotencyKey;
use App\Billing\Domain\Model\Invoice as InvoiceAggregate;
use App\Billing\Domain\Model\InvoiceId;
use App\Billing\Domain\Model\InvoiceRepository;
use App\Billing\Domain\Model\LineItem;
use App\Billing\Domain\Model\Money;
use App\Billing\Domain\Tax\VatPolicy;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IssueInvoiceHandler
{
    public function __construct(
        private InvoiceRepository $invoices,
        private VatPolicy $vat,
        private Clock $clock,
        private PersistInvoice $persist,
    ) {
    }

    public function __invoke(IssueInvoice $command): IssueInvoiceResult
    {
        $existing = $this->invoices->findByClientRequestId($command->clientRequestId);
        if ($existing !== null) {
            if (!hash_equals($existing->fingerprint(), $command->fingerprint())) {
                throw new ConflictingIdempotencyKey($command->clientRequestId);
            }

            return new IssueInvoiceResult(InvoiceView::fromInvoice($existing), replayed: true);
        }

        $currency = strtoupper($command->currency);
        $invoice = InvoiceAggregate::open(
            InvoiceId::generate(),
            $command->customerId,
            $command->countryCode,
            $currency,
            $command->clientRequestId,
            $command->fingerprint(),
            array_map(
                static fn (LineInput $line): LineItem => new LineItem(
                    $line->description,
                    $line->quantity,
                    new Money($line->unitPriceMinor, $currency),
                ),
                $command->lines,
            ),
        );

        $invoice->issue($this->vat, $this->clock);
        $this->persist->save($invoice);

        return new IssueInvoiceResult(InvoiceView::fromInvoice($invoice), replayed: false);
    }
}
