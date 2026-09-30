<?php

declare(strict_types=1);

namespace App\Billing\Domain\Exception;

final class UnknownTaxJurisdiction extends BillingException
{
    public function __construct(string $countryCode)
    {
        parent::__construct(sprintf('No VAT rate is configured for country %s.', strtoupper($countryCode)));
    }
}
