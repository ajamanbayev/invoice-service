<?php

declare(strict_types=1);

namespace App\Billing\Domain\Event;

interface BillingEvent
{
    public function invoiceId(): string;
}
