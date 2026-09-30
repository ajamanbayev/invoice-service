<?php

declare(strict_types=1);

namespace App\Billing\Domain\Tax;

final readonly class VatRate
{
    public function __construct(public int $basisPoints)
    {
        if ($basisPoints < 0 || $basisPoints > 4_000) {
            throw new \InvalidArgumentException('VAT rate must be between 0 and 4000 basis points.');
        }
    }
}
