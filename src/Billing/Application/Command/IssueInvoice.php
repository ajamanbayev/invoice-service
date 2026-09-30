<?php

declare(strict_types=1);

namespace App\Billing\Application\Command;

final readonly class IssueInvoice
{
    /**
     * @param list<LineInput> $lines
     */
    public function __construct(
        public string $clientRequestId,
        public string $customerId,
        public string $countryCode,
        public string $currency,
        public array $lines,
    ) {
    }

    public function fingerprint(): string
    {
        $lines = array_map(
            static fn (LineInput $line): array => [
                trim($line->description),
                $line->quantity,
                $line->unitPriceMinor,
            ],
            $this->lines,
        );

        return hash('sha256', json_encode([
            trim($this->customerId),
            strtoupper(trim($this->countryCode)),
            strtoupper(trim($this->currency)),
            $lines,
        ], JSON_THROW_ON_ERROR));
    }
}
