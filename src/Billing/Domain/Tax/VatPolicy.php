<?php

declare(strict_types=1);

namespace App\Billing\Domain\Tax;

use App\Billing\Domain\Exception\UnknownTaxJurisdiction;

interface VatPolicy
{
    /**
     * @throws UnknownTaxJurisdiction
     */
    public function rateFor(string $countryCode): VatRate;
}
