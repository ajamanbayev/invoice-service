<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ValidationFailedException extends \RuntimeException
{
    public function __construct(public readonly ConstraintViolationListInterface $violations)
    {
        parent::__construct('The request body is invalid.');
    }
}
