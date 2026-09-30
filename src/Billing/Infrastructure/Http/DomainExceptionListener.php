<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http;

use App\Billing\Domain\Exception\BillingException;
use App\Billing\Domain\Exception\ConflictingIdempotencyKey;
use App\Billing\Domain\Exception\InvalidInvoiceId;
use App\Billing\Domain\Exception\InvoiceNotFound;
use App\Billing\Infrastructure\Http\Exception\MalformedPayloadException;
use App\Billing\Infrastructure\Http\Exception\ValidationFailedException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolationInterface;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 20)]
final readonly class DomainExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $problem = $this->problem($throwable);
        if ($problem === null) {
            return;
        }

        $response = new JsonResponse($problem['body'], $problem['status']);
        $response->headers->set('Content-Type', 'application/problem+json');
        $event->setResponse($response);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}|null
     */
    private function problem(\Throwable $throwable): ?array
    {
        if ($throwable instanceof ValidationFailedException) {
            $errors = [];
            foreach ($throwable->violations as $violation) {
                if (!$violation instanceof ConstraintViolationInterface) {
                    continue;
                }
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => (string) $violation->getMessage(),
                ];
            }

            return [
                'status' => 422,
                'body' => $this->body('validation', 'Validation failed', 422, $throwable->getMessage(), [
                    'errors' => $errors,
                ]),
            ];
        }

        if ($throwable instanceof MalformedPayloadException) {
            return [
                'status' => 400,
                'body' => $this->body('malformed-payload', 'Malformed request', 400, $throwable->getMessage()),
            ];
        }

        if ($throwable instanceof InvalidInvoiceId) {
            return [
                'status' => 400,
                'body' => $this->body('invalid-invoice-id', 'Invalid invoice id', 400, $throwable->getMessage()),
            ];
        }

        if ($throwable instanceof InvoiceNotFound) {
            return [
                'status' => 404,
                'body' => $this->body('invoice-not-found', 'Invoice not found', 404, $throwable->getMessage()),
            ];
        }

        if ($throwable instanceof ConflictingIdempotencyKey) {
            return [
                'status' => 409,
                'body' => $this->body('idempotency-conflict', 'Idempotency conflict', 409, $throwable->getMessage()),
            ];
        }

        if ($throwable instanceof BillingException) {
            return [
                'status' => 422,
                'body' => $this->body('billing-rule', 'Billing rule rejected the command', 422, $throwable->getMessage()),
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function body(string $type, string $title, int $status, string $detail, array $extra = []): array
    {
        return [
            'type' => 'urn:invoice-service:problem:'.$type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            ...$extra,
        ];
    }
}
