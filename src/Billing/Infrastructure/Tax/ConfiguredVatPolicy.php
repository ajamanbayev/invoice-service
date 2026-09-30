<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Tax;

use App\Billing\Domain\Exception\UnknownTaxJurisdiction;
use App\Billing\Domain\Tax\VatPolicy;
use App\Billing\Domain\Tax\VatRate;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: VatPolicy::class)]
final readonly class ConfiguredVatPolicy implements VatPolicy
{
    /**
     * @param array<string, int> $basisPointsByCountry
     */
    public function __construct(private array $basisPointsByCountry)
    {
    }

    public function rateFor(string $countryCode): VatRate
    {
        $code = strtoupper($countryCode);
        if (!isset($this->basisPointsByCountry[$code])) {
            throw new UnknownTaxJurisdiction($code);
        }

        return new VatRate($this->basisPointsByCountry[$code]);
    }
}
