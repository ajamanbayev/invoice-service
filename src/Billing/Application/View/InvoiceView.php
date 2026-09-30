<?php

declare(strict_types=1);

namespace App\Billing\Application\View;

use App\Billing\Domain\Model\Invoice;
use App\Billing\Domain\Model\LineItem;

final readonly class InvoiceView implements \JsonSerializable
{
    /**
     * @param list<LineView> $lines
     */
    public function __construct(
        public string $id,
        public string $customerId,
        public string $countryCode,
        public string $currency,
        public string $status,
        public ?int $netMinor,
        public ?int $taxMinor,
        public ?int $grossMinor,
        public ?int $vatBasisPoints,
        public array $lines,
        public ?string $issuedAt,
        public ?string $paidAt,
        public ?string $voidedAt,
        public ?string $voidReason,
    ) {
    }

    public static function fromInvoice(Invoice $invoice): self
    {
        return new self(
            $invoice->id()->toString(),
            $invoice->customerId(),
            $invoice->countryCode(),
            $invoice->currency(),
            $invoice->status()->value,
            $invoice->net()?->amountMinor,
            $invoice->tax()?->amountMinor,
            $invoice->gross()?->amountMinor,
            $invoice->vatBasisPoints(),
            array_map(
                static fn (LineItem $line): LineView => new LineView(
                    $line->description,
                    $line->quantity,
                    $line->unitPrice->amountMinor,
                ),
                $invoice->lines(),
            ),
            $invoice->issuedAt()?->format(\DateTimeInterface::ATOM),
            $invoice->paidAt()?->format(\DateTimeInterface::ATOM),
            $invoice->voidedAt()?->format(\DateTimeInterface::ATOM),
            $invoice->voidReason(),
        );
    }

    /**
     * @return array{
     *     id: string,
     *     customerId: string,
     *     countryCode: string,
     *     currency: string,
     *     status: string,
     *     netMinor: ?int,
     *     taxMinor: ?int,
     *     grossMinor: ?int,
     *     vatBasisPoints: ?int,
     *     lines: list<LineView>,
     *     issuedAt: ?string,
     *     paidAt: ?string,
     *     voidedAt: ?string,
     *     voidReason: ?string
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customerId,
            'countryCode' => $this->countryCode,
            'currency' => $this->currency,
            'status' => $this->status,
            'netMinor' => $this->netMinor,
            'taxMinor' => $this->taxMinor,
            'grossMinor' => $this->grossMinor,
            'vatBasisPoints' => $this->vatBasisPoints,
            'lines' => $this->lines,
            'issuedAt' => $this->issuedAt,
            'paidAt' => $this->paidAt,
            'voidedAt' => $this->voidedAt,
            'voidReason' => $this->voidReason,
        ];
    }
}
