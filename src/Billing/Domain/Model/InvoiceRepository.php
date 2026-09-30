<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

interface InvoiceRepository
{
    public function get(InvoiceId $id): Invoice;

    public function findByClientRequestId(string $clientRequestId): ?Invoice;

    public function save(Invoice $invoice): void;
}
