<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Time;

use App\Billing\Domain\Clock;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: Clock::class)]
final readonly class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
