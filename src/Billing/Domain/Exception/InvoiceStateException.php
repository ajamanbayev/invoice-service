<?php

declare(strict_types=1);

namespace App\Billing\Domain\Exception;

use App\Billing\Domain\Model\InvoiceId;
use App\Billing\Domain\Model\InvoiceStatus;

final class InvoiceStateException extends BillingException
{
    public static function cannotIssue(InvoiceId $id, InvoiceStatus $status): self
    {
        return new self(sprintf(
            'Invoice %s cannot be issued from status "%s".',
            $id->toString(),
            $status->value,
        ));
    }

    public static function empty(InvoiceId $id): self
    {
        return new self(sprintf('Invoice %s has no lines.', $id->toString()));
    }

    public static function linesClosed(InvoiceId $id): self
    {
        return new self(sprintf('Invoice %s no longer accepts lines.', $id->toString()));
    }

    public static function cannotPay(InvoiceId $id, InvoiceStatus $status): self
    {
        return new self(sprintf(
            'Invoice %s cannot be paid from status "%s".',
            $id->toString(),
            $status->value,
        ));
    }

    public static function cannotVoid(InvoiceId $id, InvoiceStatus $status): self
    {
        return new self(sprintf(
            'Invoice %s cannot be voided from status "%s".',
            $id->toString(),
            $status->value,
        ));
    }
}
