<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http\Request;

use App\Billing\Application\Command\VoidInvoice;
use App\Billing\Infrastructure\Http\Exception\MalformedPayloadException;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class VoidInvoiceRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public string $reason,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        if (!array_key_exists('reason', $payload)) {
            return new self('');
        }

        if (!is_string($payload['reason'])) {
            throw new MalformedPayloadException('Field "reason" must be a string.');
        }

        return new self(trim($payload['reason']));
    }

    public function toCommand(string $invoiceId): VoidInvoice
    {
        return new VoidInvoice($invoiceId, $this->reason);
    }
}
