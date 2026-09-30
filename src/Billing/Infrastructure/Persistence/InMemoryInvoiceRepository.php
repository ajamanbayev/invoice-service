<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Persistence;

use App\Billing\Domain\Exception\ConflictingIdempotencyKey;
use App\Billing\Domain\Exception\InvoiceNotFound;
use App\Billing\Domain\Model\Invoice;
use App\Billing\Domain\Model\InvoiceId;
use App\Billing\Domain\Model\InvoiceRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Process memory stands in for a database so the review can run without one.
 * A Doctrine adapter would implement the same port; client_request_id would be a unique key.
 */
#[AsAlias(id: InvoiceRepository::class)]
final class InMemoryInvoiceRepository implements InvoiceRepository
{
    /** @var array<string, Invoice> */
    private array $byId = [];

    /** @var array<string, string> */
    private array $idByClientRequest = [];

    public function get(InvoiceId $id): Invoice
    {
        return $this->byId[$id->toString()] ?? throw new InvoiceNotFound($id);
    }

    public function findByClientRequestId(string $clientRequestId): ?Invoice
    {
        $id = $this->idByClientRequest[strtolower($clientRequestId)] ?? null;
        if ($id === null) {
            return null;
        }

        return $this->byId[$id] ?? null;
    }

    public function save(Invoice $invoice): void
    {
        $clientRequestId = $invoice->clientRequestId();
        $existingId = $this->idByClientRequest[$clientRequestId] ?? null;
        if ($existingId !== null && $existingId !== $invoice->id()->toString()) {
            throw new ConflictingIdempotencyKey($clientRequestId);
        }

        $this->byId[$invoice->id()->toString()] = $invoice;
        $this->idByClientRequest[$clientRequestId] = $invoice->id()->toString();
    }
}
