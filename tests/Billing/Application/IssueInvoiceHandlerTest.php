<?php

declare(strict_types=1);

namespace App\Tests\Billing\Application;

use App\Billing\Application\Command\IssueInvoice;
use App\Billing\Application\Command\LineInput;
use App\Billing\Application\Event\DomainEventDispatcher;
use App\Billing\Application\IssueInvoiceHandler;
use App\Billing\Application\PersistInvoice;
use App\Billing\Domain\Event\BillingEvent;
use App\Billing\Domain\Event\InvoiceIssued;
use App\Billing\Domain\Exception\ConflictingIdempotencyKey;
use App\Billing\Infrastructure\Persistence\InMemoryInvoiceRepository;
use App\Billing\Infrastructure\Tax\ConfiguredVatPolicy;
use App\Tests\Double\FrozenClock;
use PHPUnit\Framework\TestCase;

final class IssueInvoiceHandlerTest extends TestCase
{
    private const REQUEST_ID = '6f1c1d3e-1c4a-4a1e-9c1a-1c1d3e1c4a1e';

    public function testIssueIsIdempotentForTheSamePayload(): void
    {
        $repository = new InMemoryInvoiceRepository();
        $dispatcher = new class implements DomainEventDispatcher {
            /** @var list<BillingEvent> */
            public array $events = [];

            public function dispatch(BillingEvent $event): void
            {
                $this->events[] = $event;
            }
        };
        $handler = new IssueInvoiceHandler(
            $repository,
            new ConfiguredVatPolicy(['DE' => 1_900]),
            new FrozenClock(new \DateTimeImmutable('2026-03-01T10:00:00+00:00')),
            new PersistInvoice($repository, $dispatcher),
        );

        $first = $handler(new IssueInvoice(self::REQUEST_ID, 'acme', 'de', 'eur', [
            new LineInput('Consulting', 2, 10_000),
        ]));
        $second = $handler(new IssueInvoice(strtoupper(self::REQUEST_ID), 'acme', 'DE', 'EUR', [
            new LineInput('Consulting', 2, 10_000),
        ]));

        self::assertFalse($first->replayed);
        self::assertTrue($second->replayed);
        self::assertSame($first->invoice->id, $second->invoice->id);
        self::assertSame(23_800, $first->invoice->grossMinor);
        self::assertSame('EUR', $first->invoice->currency);
        self::assertSame('DE', $first->invoice->countryCode);
        self::assertCount(1, $dispatcher->events);
        self::assertInstanceOf(InvoiceIssued::class, $dispatcher->events[0]);
    }

    public function testSameKeyWithAnotherPayloadConflicts(): void
    {
        $repository = new InMemoryInvoiceRepository();
        $handler = new IssueInvoiceHandler(
            $repository,
            new ConfiguredVatPolicy(['DE' => 1_900]),
            new FrozenClock(new \DateTimeImmutable('2026-03-01T10:00:00+00:00')),
            new PersistInvoice($repository, new class implements DomainEventDispatcher {
                public function dispatch(BillingEvent $event): void
                {
                }
            }),
        );

        $handler(new IssueInvoice(self::REQUEST_ID, 'acme', 'DE', 'EUR', [
            new LineInput('Consulting', 2, 10_000),
        ]));

        $this->expectException(ConflictingIdempotencyKey::class);

        $handler(new IssueInvoice(self::REQUEST_ID, 'acme', 'DE', 'EUR', [
            new LineInput('Consulting', 1, 10_000),
        ]));
    }
}
