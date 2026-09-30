<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http\Request;

use App\Billing\Infrastructure\Http\Exception\MalformedPayloadException;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class IssueInvoiceLineRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $description,
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(10_000)]
        public int $quantity,
        #[Assert\PositiveOrZero]
        public int $unitPriceMinor,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload, int $index): self
    {
        return new self(
            self::stringField($payload, 'description', $index),
            self::intField($payload, 'quantity', $index),
            self::intField($payload, 'unitPriceMinor', $index),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function stringField(array $payload, string $field, int $index): string
    {
        if (!array_key_exists($field, $payload)) {
            return '';
        }

        $value = $payload[$field];
        if (!is_string($value)) {
            throw new MalformedPayloadException(sprintf('Field "lines[%d].%s" must be a string.', $index, $field));
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function intField(array $payload, string $field, int $index): int
    {
        if (!array_key_exists($field, $payload) || !is_int($payload[$field])) {
            throw new MalformedPayloadException(sprintf('Field "lines[%d].%s" must be an integer.', $index, $field));
        }

        return $payload[$field];
    }
}
