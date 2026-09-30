<?php

declare(strict_types=1);

namespace App\Billing\Domain\Exception;

final class ConflictingIdempotencyKey extends BillingException
{
    public function __construct(string $clientRequestId)
    {
        parent::__construct(sprintf(
            'Client request %s was already used with a different payload.',
            $clientRequestId,
        ));
    }
}
