<?php

declare(strict_types=1);

namespace CampBuddy\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;

/**
 * Synchronizer-token CSRF check for state-changing admin requests.
 * CampBuddy::csrfToken() (a view helper) prints the token into every
 * admin form; this middleware validates it on POST.
 */
final class CsrfMiddleware
{
    public function __invoke(ServerRequestInterface $request, Handler $handler): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $body = (array) $request->getParsedBody();
            $submitted = (string) ($body['csrf_token'] ?? '');
            $expected = (string) ($_SESSION['csrf_token'] ?? '');

            if ($expected === '' || !hash_equals($expected, $submitted)) {
                $response = new Response(403);
                $response->getBody()->write('Invalid or expired form submission. Please go back and try again.');
                return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }
        }

        return $handler->handle($request);
    }

    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}
