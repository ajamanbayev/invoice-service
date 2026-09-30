<?php

declare(strict_types=1);

namespace App\Billing\Domain;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
