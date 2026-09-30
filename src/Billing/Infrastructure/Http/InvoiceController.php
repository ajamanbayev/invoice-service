<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http;

use App\Billing\Application\Command\IssueInvoiceResult;
use App\Billing\Application\Command\PayInvoice;
use App\Billing\Application\GetInvoiceHandler;
use App\Billing\Application\View\InvoiceView;
use App\Billing\Infrastructure\Http\Exception\MalformedPayloadException;
use App\Billing\Infrastructure\Http\Exception\ValidationFailedException;
use App\Billing\Infrastructure\Http\Request\IssueInvoiceRequest;
use App\Billing\Infrastructure\Http\Request\VoidInvoiceRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class InvoiceController
{
    public function __construct(
        private MessageBusInterface $bus,
        private GetInvoiceHandler $invoices,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/invoices', name: 'invoice_issue', methods: ['POST'])]
    public function issue(Request $request): JsonResponse
    {
        $dto = IssueInvoiceRequest::fromPayload($this->decode($request));
        $this->assertValid($dto);

        $result = $this->handled($dto->toCommand());
        if (!$result instanceof IssueInvoiceResult) {
            throw new \LogicException('Issue invoice handler returned an unexpected result.');
        }

        return new JsonResponse(
            $result->invoice,
            $result->replayed ? Response::HTTP_OK : Response::HTTP_CREATED,
            ['Location' => '/api/invoices/'.$result->invoice->id],
        );
    }

    #[Route('/api/invoices/{id}', name: 'invoice_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        return new JsonResponse($this->invoices->handle($id));
    }

    #[Route('/api/invoices/{id}/pay', name: 'invoice_pay', methods: ['POST'])]
    public function pay(string $id): JsonResponse
    {
        $view = $this->handled(new PayInvoice($id));
        if (!$view instanceof InvoiceView) {
            throw new \LogicException('Pay invoice handler returned an unexpected result.');
        }

        return new JsonResponse($view);
    }

    #[Route('/api/invoices/{id}/void', name: 'invoice_void', methods: ['POST'])]
    public function void(string $id, Request $request): JsonResponse
    {
        $dto = VoidInvoiceRequest::fromPayload($this->decode($request));
        $this->assertValid($dto);

        $view = $this->handled($dto->toCommand($id));
        if (!$view instanceof InvoiceView) {
            throw new \LogicException('Void invoice handler returned an unexpected result.');
        }

        return new JsonResponse($view);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Request $request): array
    {
        if ($request->getContent() === '') {
            throw new MalformedPayloadException('Request body is empty.');
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new MalformedPayloadException('Request body must be valid JSON.');
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new MalformedPayloadException('Request body must be a JSON object.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    private function assertValid(object $dto): void
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            throw new ValidationFailedException($violations);
        }
    }

    private function handled(object $command): mixed
    {
        $stamp = $this->bus->dispatch($command)->last(HandledStamp::class);
        if (!$stamp instanceof HandledStamp) {
            throw new \LogicException(sprintf('No handler for %s.', $command::class));
        }

        return $stamp->getResult();
    }
}
