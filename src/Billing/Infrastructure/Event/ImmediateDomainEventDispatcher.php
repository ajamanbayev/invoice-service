<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Event;

use App\Billing\Application\Event\DomainEventDispatcher;
use App\Billing\Application\Event\DomainEventListener;
use App\Billing\Domain\Event\BillingEvent;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

#[AsAlias(id: DomainEventDispatcher::class)]
final readonly class ImmediateDomainEventDispatcher implements DomainEventDispatcher
{
    /**
     * @param iterable<DomainEventListener> $listeners
     */
    public function __construct(
        #[TaggedIterator('billing.domain_listener')]
        private iterable $listeners,
    ) {
    }

    public function dispatch(BillingEvent $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener->on($event);
        }
    }
}
