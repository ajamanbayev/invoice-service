<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Billing\Domain\Clock;

final readonly class FrozenClock implements Clock
{
    public function __construct(private \DateTimeImmutable $instant)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->instant;
    }
}
