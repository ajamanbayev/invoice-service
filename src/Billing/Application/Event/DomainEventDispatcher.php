<?php

declare(strict_types=1);

namespace App\Billing\Application\Event;

use App\Billing\Domain\Event\BillingEvent;

interface DomainEventDispatcher
{
    public function dispatch(BillingEvent $event): void;
}
