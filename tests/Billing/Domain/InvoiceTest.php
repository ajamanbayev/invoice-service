<?php

declare(strict_types=1);

namespace App\Tests\Billing\Domain;

use App\Billing\Domain\Event\InvoiceIssued;
use App\Billing\Domain\Event\InvoicePaid;
use App\Billing\Domain\Exception\CurrencyMismatch;
use App\Billing\Domain\Exception\InvalidInvoiceId;
use App\Billing\Domain\Exception\InvoiceStateException;
use App\Billing\Domain\Exception\UnknownTaxJurisdiction;
use App\Billing\Domain\Model\Invoice;
use App\Billing\Domain\Model\InvoiceId;
use App\Billing\Domain\Model\InvoiceStatus;
use App\Billing\Domain\Model\LineItem;
use App\Billing\Domain\Model\Money;
use App\Billing\Domain\Tax\VatPolicy;
use App\Billing\Domain\Tax\VatRate;
use App\Tests\Double\FrozenClock;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    private const REQUEST_ID = '6f1c1d3e-1c4a-4a1e-9c1a-1c1d3e1c4a1e';

    public function testIssuesFrozenTotalsAndOneEvent(): void
    {
        $invoice = $this->invoice();
        $issuedAt = new \DateTimeImmutable('2026-03-01T10:00:00+00:00');

        $invoice->issue($this->germanVat(), new FrozenClock($issuedAt));

        self::assertSame(InvoiceStatus::Issued, $invoice->status());
        self::assertSame(20_000, $invoice->net()?->amountMinor);
        self::assertSame(3_800, $invoice->tax()?->amountMinor);
        self::assertSame(23_800, $invoice->gross()?->amountMinor);
        self::assertSame(1_900, $invoice->vatBasisPoints());
        self::assertSame($issuedAt, $invoice->issuedAt());

        $events = $invoice->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InvoiceIssued::class, $events[0]);
        self::assertSame([], $invoice->releaseEvents());
    }

    public function testRejectsEmptyInvoice(): void
    {
        $invoice = $this->invoice(lines: []);

        $this->expectException(InvoiceStateException::class);

        $invoice->issue($this->germanVat(), new FrozenClock(new \DateTimeImmutable('2026-03-01T10:00:00+00:00')));
    }

    public function testRejectsUnknownCountryBeforeChangingStatus(): void
    {
        $invoice = $this->invoice(country: 'FR');

        try {
            $invoice->issue($this->germanVat(), new FrozenClock(new \DateTimeImmutable('2026-03-01T10:00:00+00:00')));
            self::fail('Unknown jurisdiction must be rejected.');
        } catch (UnknownTaxJurisdiction) {
            self::assertSame(InvoiceStatus::Draft, $invoice->status());
            self::assertNull($invoice->gross());
        }
    }

    public function testRejectsLineInAnotherCurrency(): void
    {
        $this->expectException(CurrencyMismatch::class);

        $this->invoice(lines: [
            new LineItem('Consulting', 1, new Money(100, 'USD')),
        ]);
    }

    public function testLinesAreClosedAfterIssue(): void
    {
        $invoice = $this->invoice();
        $invoice->issue($this->germanVat(), new FrozenClock(new \DateTimeImmutable('2026-03-01T10:00:00+00:00')));

        $this->expectException(InvoiceStateException::class);

        $invoice->addLine(new LineItem('Extra', 1, new Money(100, 'EUR')));
    }

    public function testPayIsIdempotentAndVoidIsNot(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-03-01T10:00:00+00:00'));
        $invoice = $this->invoice();
        $invoice->issue($this->germanVat(), $clock);
        $invoice->releaseEvents();

        $invoice->pay($clock);
        $invoice->pay($clock);

        $events = $invoice->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InvoicePaid::class, $events[0]);

        $this->expectException(InvoiceStateException::class);
        $invoice->void('customer cancelled', $clock);
    }

    public function testCannotPayADraft(): void
    {
        $this->expectException(InvoiceStateException::class);

        $this->invoice()->pay(new FrozenClock(new \DateTimeImmutable('2026-03-01T10:00:00+00:00')));
    }

    public function testVoidsOnlyAnIssuedInvoice(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-03-02T10:00:00+00:00'));
        $invoice = $this->invoice();
        $invoice->issue($this->germanVat(), $clock);

        $invoice->void('duplicate', $clock);

        self::assertSame(InvoiceStatus::Void, $invoice->status());
        self::assertSame('duplicate', $invoice->voidReason());
    }

    public function testGeneratedIdRoundtrips(): void
    {
        $id = InvoiceId::generate();

        self::assertTrue($id->equals(InvoiceId::fromString($id->toString())));
    }

    public function testRejectsMalformedId(): void
    {
        $this->expectException(InvalidInvoiceId::class);

        InvoiceId::fromString('not-a-uuid');
    }

    /**
     * @param list<LineItem>|null $lines
     */
    private function invoice(?array $lines = null, string $country = 'DE'): Invoice
    {
        return Invoice::open(
            InvoiceId::generate(),
            'acme',
            $country,
            'EUR',
            self::REQUEST_ID,
            hash('sha256', 'review-payload'),
            $lines ?? [new LineItem('Consulting', 2, new Money(10_000, 'EUR'))],
        );
    }

    private function germanVat(): VatPolicy
    {
        return new class implements VatPolicy {
            public function rateFor(string $countryCode): VatRate
            {
                if ($countryCode !== 'DE') {
                    throw new UnknownTaxJurisdiction($countryCode);
                }

                return new VatRate(1_900);
            }
        };
    }
}
