<?php

declare(strict_types=1);

namespace CampBuddy\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Interfaces\ErrorHandlerInterface;
use Slim\Psr7\Response;
use Throwable;

/**
 * Every error becomes clean JSON. In production, no message/stack trace
 * ever reaches the client — full detail is logged server-side via Monolog
 * instead.
 */
final class JsonErrorMiddleware implements ErrorHandlerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        $status = $exception instanceof HttpException ? $exception->getCode() : 500;
        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        if ($status >= 500) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        } else {
            $this->logger->notice($exception->getMessage(), ['status' => $status, 'path' => (string) $request->getUri()]);
        }

        $payload = ['error' => $this->publicMessage($status)];
        if ($this->debug) {
            $payload['debug'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        $response = new Response($status);
        $response->getBody()->write((string) json_encode($payload));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function publicMessage(int $status): string
    {
        return match ($status) {
            404 => 'Not found.',
            405 => 'Method not allowed.',
            429 => 'Too many requests, please slow down.',
            default => $status >= 500 ? 'Something went wrong. Please try again shortly.' : 'Request could not be processed.',
        };
    }
}
