<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http\Request;

use App\Billing\Application\Command\IssueInvoice;
use App\Billing\Application\Command\LineInput;
use App\Billing\Infrastructure\Http\Exception\MalformedPayloadException;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class IssueInvoiceRequest
{
    /**
     * @param list<IssueInvoiceLineRequest> $lines
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $clientRequestId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        #[Assert\Regex(pattern: '/^[A-Za-z0-9_-]+$/')]
        public string $customerId,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[A-Z]{2}$/')]
        public string $countryCode,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[A-Z]{3}$/')]
        public string $currency,
        #[Assert\Count(min: 1, max: 100)]
        #[Assert\Valid]
        public array $lines,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $lines = [];
        if (array_key_exists('lines', $payload)) {
            if (!is_array($payload['lines']) || !array_is_list($payload['lines'])) {
                throw new MalformedPayloadException('Field "lines" must be a JSON array.');
            }

            foreach ($payload['lines'] as $index => $line) {
                if (!is_array($line) || array_is_list($line)) {
                    throw new MalformedPayloadException(sprintf('Field "lines[%d]" must be an object.', $index));
                }

                $lines[] = IssueInvoiceLineRequest::fromPayload($line, $index);
            }
        }

        $country = self::stringField($payload, 'countryCode');
        $currency = self::stringField($payload, 'currency');

        return new self(
            self::stringField($payload, 'clientRequestId'),
            self::stringField($payload, 'customerId'),
            $country === '' ? '' : strtoupper($country),
            $currency === '' ? '' : strtoupper($currency),
            $lines,
        );
    }

    public function toCommand(): IssueInvoice
    {
        return new IssueInvoice(
            $this->clientRequestId,
            $this->customerId,
            $this->countryCode,
            $this->currency,
            array_map(
                static fn (IssueInvoiceLineRequest $line): LineInput => new LineInput(
                    $line->description,
                    $line->quantity,
                    $line->unitPriceMinor,
                ),
                $this->lines,
            ),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function stringField(array $payload, string $field): string
    {
        if (!array_key_exists($field, $payload)) {
            return '';
        }

        $value = $payload[$field];
        if (!is_string($value)) {
            throw new MalformedPayloadException(sprintf('Field "%s" must be a string.', $field));
        }

        return trim($value);
    }
}
