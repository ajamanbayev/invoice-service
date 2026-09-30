<?php

declare(strict_types=1);

namespace App\Billing\Application\View;

final readonly class LineView implements \JsonSerializable
{
    public function __construct(
        public string $description,
        public int $quantity,
        public int $unitPriceMinor,
    ) {
    }

    /**
     * @return array{description: string, quantity: int, unitPriceMinor: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitPriceMinor' => $this->unitPriceMinor,
        ];
    }
}
