<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Event;

use App\Billing\Application\Event\DomainEventListener;
use App\Billing\Domain\Event\BillingEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('billing.domain_listener')]
final readonly class LogBillingEvent implements DomainEventListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function on(BillingEvent $event): void
    {
        $this->logger->info('Billing event recorded', [
            'event' => $event::class,
            'invoiceId' => $event->invoiceId(),
        ]);
    }
}
