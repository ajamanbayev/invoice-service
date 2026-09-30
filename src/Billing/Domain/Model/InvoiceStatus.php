<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Void = 'void';
}
