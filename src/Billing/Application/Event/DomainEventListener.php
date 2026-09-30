<?php

declare(strict_types=1);

namespace App\Billing\Application\Event;

use App\Billing\Domain\Event\BillingEvent;

interface DomainEventListener
{
    public function on(BillingEvent $event): void;
}
