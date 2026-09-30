<?php

declare(strict_types=1);

namespace App\Billing\Domain\Exception;

use App\Billing\Domain\Model\InvoiceId;

final class InvoiceNotFound extends BillingException
{
    public function __construct(InvoiceId $id)
    {
        parent::__construct(sprintf('Invoice %s was not found.', $id->toString()));
    }
}
