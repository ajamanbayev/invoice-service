<?php

declare(strict_types=1);

namespace App\Tests\Billing\Infrastructure\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InvoiceControllerTest extends WebTestCase
{
    private const REQUEST_ID = '6f1c1d3e-1c4a-4a1e-9c1a-1c1d3e1c4a1e';

    public function testIssuePayAndReplay(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/invoices', $this->payload());

        self::assertSame(201, $client->getResponse()->getStatusCode());
        /** @var array<string, mixed> $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('issued', $created['status']);
        self::assertSame(20_000, $created['netMinor']);
        self::assertSame(3_800, $created['taxMinor']);
        self::assertSame(23_800, $created['grossMinor']);
        self::assertSame('/api/invoices/'.$created['id'], $client->getResponse()->headers->get('Location'));

        $client->jsonRequest('POST', '/api/invoices', $this->payload());
        self::assertSame(200, $client->getResponse()->getStatusCode());
        /** @var array<string, mixed> $replayed */
        $replayed = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($created['id'], $replayed['id']);

        $client->request('POST', '/api/invoices/'.$created['id'].'/pay');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        /** @var array<string, mixed> $paid */
        $paid = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('paid', $paid['status']);

        $client->request('POST', '/api/invoices/'.$created['id'].'/pay');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        /** @var array<string, mixed> $paidAgain */
        $paidAgain = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($paid['paidAt'], $paidAgain['paidAt']);

        $client->request('GET', '/api/invoices/'.$created['id']);
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testConflictAndBusinessRules(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/invoices', $this->payload());
        self::assertSame(201, $client->getResponse()->getStatusCode());

        $other = $this->payload();
        $other['lines'][0]['quantity'] = 1;
        $client->jsonRequest('POST', '/api/invoices', $other);

        self::assertSame(409, $client->getResponse()->getStatusCode());
        self::assertSame('application/problem+json', $client->getResponse()->headers->get('Content-Type'));

        $client->jsonRequest('POST', '/api/invoices', $this->payload(country: 'XX', requestId: '3f1c1d3e-1c4a-4a1e-9c1a-1c1d3e1c4a1e'));
        self::assertSame(422, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/invoices', $this->payload(lines: []));
        self::assertSame(422, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/invoices/not-a-uuid');
        self::assertSame(400, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/invoices/6ba7b810-9dad-11d1-80b4-00c04fd430c8');
        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    /**
     * @param list<array{description: string, quantity: int, unitPriceMinor: int}>|null $lines
     *
     * @return array<string, mixed>
     */
    private function payload(
        string $country = 'DE',
        ?string $requestId = null,
        ?array $lines = null,
    ): array {
        return [
            'clientRequestId' => $requestId ?? self::REQUEST_ID,
            'customerId' => 'acme',
            'countryCode' => $country,
            'currency' => 'EUR',
            'lines' => $lines ?? [
                ['description' => 'Consulting', 'quantity' => 2, 'unitPriceMinor' => 10_000],
            ],
        ];
    }
}
