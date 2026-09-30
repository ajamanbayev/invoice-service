<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

use App\Billing\Domain\Clock;
use App\Billing\Domain\Event\BillingEvent;
use App\Billing\Domain\Event\InvoiceIssued;
use App\Billing\Domain\Event\InvoicePaid;
use App\Billing\Domain\Event\InvoiceVoided;
use App\Billing\Domain\Exception\CurrencyMismatch;
use App\Billing\Domain\Exception\InvoiceStateException;
use App\Billing\Domain\Tax\VatPolicy;

final class Invoice
{
    /** @var list<LineItem> */
    private array $lines = [];

    /** @var list<BillingEvent> */
    private array $recordedEvents = [];

    private InvoiceStatus $status = InvoiceStatus::Draft;

    private ?Money $net = null;

    private ?Money $tax = null;

    private ?Money $gross = null;

    private ?int $vatBasisPoints = null;

    private ?\DateTimeImmutable $issuedAt = null;

    private ?\DateTimeImmutable $paidAt = null;

    private ?\DateTimeImmutable $voidedAt = null;

    private ?string $voidReason = null;

    /**
     * @param list<LineItem> $lines
     */
    private function __construct(
        private readonly InvoiceId $id,
        private readonly string $customerId,
        private readonly string $countryCode,
        private readonly string $currency,
        private readonly string $clientRequestId,
        private readonly string $fingerprint,
        array $lines,
    ) {
        foreach ($lines as $line) {
            $this->addLine($line);
        }
    }

    /**
     * @param list<LineItem> $lines
     */
    public static function open(
        InvoiceId $id,
        string $customerId,
        string $countryCode,
        string $currency,
        string $clientRequestId,
        string $fingerprint,
        array $lines,
    ): self {
        $customerId = trim($customerId);
        if ($customerId === '' || strlen($customerId) > 64 || 1 !== preg_match('/^[A-Za-z0-9_-]+$/', $customerId)) {
            throw new \InvalidArgumentException('Customer id must be 1–64 characters [A-Za-z0-9_-].');
        }

        $countryCode = strtoupper(trim($countryCode));
        if (1 !== preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new \InvalidArgumentException('Country must be an ISO 3166-1 alpha-2 code.');
        }

        $currency = strtoupper(trim($currency));
        if (1 !== preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('Currency must be an ISO-4217 code.');
        }

        if (1 !== preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $clientRequestId,
        )) {
            throw new \InvalidArgumentException('Client request id must be a UUID.');
        }

        if (1 !== preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
            throw new \InvalidArgumentException('Fingerprint must be a sha256 hex digest.');
        }

        return new self(
            $id,
            $customerId,
            $countryCode,
            $currency,
            strtolower($clientRequestId),
            $fingerprint,
            $lines,
        );
    }

    public function addLine(LineItem $line): void
    {
        if ($this->status !== InvoiceStatus::Draft) {
            throw InvoiceStateException::linesClosed($this->id);
        }

        if ($line->unitPrice->currency !== $this->currency) {
            throw CurrencyMismatch::between($this->currency, $line->unitPrice->currency);
        }

        $this->lines[] = $line;
    }

    public function issue(VatPolicy $vat, Clock $clock): void
    {
        if ($this->status !== InvoiceStatus::Draft) {
            throw InvoiceStateException::cannotIssue($this->id, $this->status);
        }

        if ($this->lines === []) {
            throw InvoiceStateException::empty($this->id);
        }

        $rate = $vat->rateFor($this->countryCode);
        $net = Money::zero($this->currency);
        foreach ($this->lines as $line) {
            $net = $net->add($line->netAmount());
        }

        $tax = $net->applyRate($rate);
        $gross = $net->add($tax);
        $issuedAt = $clock->now();

        $this->net = $net;
        $this->tax = $tax;
        $this->gross = $gross;
        $this->vatBasisPoints = $rate->basisPoints;
        $this->issuedAt = $issuedAt;
        $this->status = InvoiceStatus::Issued;
        $this->recordedEvents[] = new InvoiceIssued(
            $this->id->toString(),
            $this->customerId,
            $gross->amountMinor,
            $gross->currency,
            $issuedAt,
        );
    }

    public function pay(Clock $clock): void
    {
        if ($this->status === InvoiceStatus::Paid) {
            return;
        }

        if ($this->status !== InvoiceStatus::Issued) {
            throw InvoiceStateException::cannotPay($this->id, $this->status);
        }

        $paidAt = $clock->now();
        $this->paidAt = $paidAt;
        $this->status = InvoiceStatus::Paid;

        $gross = $this->gross ?? throw new \LogicException('Issued invoice is missing a gross amount.');
        $this->recordedEvents[] = new InvoicePaid(
            $this->id->toString(),
            $gross->amountMinor,
            $gross->currency,
            $paidAt,
        );
    }

    public function void(string $reason, Clock $clock): void
    {
        if ($this->status !== InvoiceStatus::Issued) {
            throw InvoiceStateException::cannotVoid($this->id, $this->status);
        }

        $reason = trim($reason);
        $length = mb_strlen($reason);
        if ($length < 1 || $length > 500) {
            throw new \InvalidArgumentException('Void reason must be 1–500 characters.');
        }

        $voidedAt = $clock->now();
        $this->voidReason = $reason;
        $this->voidedAt = $voidedAt;
        $this->status = InvoiceStatus::Void;
        $this->recordedEvents[] = new InvoiceVoided($this->id->toString(), $reason, $voidedAt);
    }

    /**
     * @return list<BillingEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    public function id(): InvoiceId
    {
        return $this->id;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function clientRequestId(): string
    {
        return $this->clientRequestId;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    public function status(): InvoiceStatus
    {
        return $this->status;
    }

    /**
     * @return list<LineItem>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function net(): ?Money
    {
        return $this->net;
    }

    public function tax(): ?Money
    {
        return $this->tax;
    }

    public function gross(): ?Money
    {
        return $this->gross;
    }

    public function vatBasisPoints(): ?int
    {
        return $this->vatBasisPoints;
    }

    public function issuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function paidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function voidedAt(): ?\DateTimeImmutable
    {
        return $this->voidedAt;
    }

    public function voidReason(): ?string
    {
        return $this->voidReason;
    }
}
