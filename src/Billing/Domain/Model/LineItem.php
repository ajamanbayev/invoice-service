<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

final readonly class LineItem
{
    public string $description;

    public function __construct(
        string $description,
        public int $quantity,
        public Money $unitPrice,
    ) {
        $description = trim($description);
        $length = mb_strlen($description);
        if ($length < 1 || $length > 200) {
            throw new \InvalidArgumentException('Line description must be 1–200 characters.');
        }

        if ($quantity < 1 || $quantity > 10_000) {
            throw new \InvalidArgumentException('Quantity must be between 1 and 10000.');
        }

        $this->description = $description;
    }

    public function netAmount(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
