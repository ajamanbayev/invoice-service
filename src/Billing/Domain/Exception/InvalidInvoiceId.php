<?php

declare(strict_types=1);

namespace App\Billing\Domain\Exception;

final class InvalidInvoiceId extends BillingException
{
    public static function becauseItIsNotUuid(): self
    {
        return new self('Invoice id must be a UUID.');
    }
}
