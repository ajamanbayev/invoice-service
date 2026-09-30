<?php

declare(strict_types=1);

namespace App\Billing\Application\Command;

final readonly class LineInput
{
    public function __construct(
        public string $description,
        public int $quantity,
        public int $unitPriceMinor,
    ) {
    }
}
