<?php

declare(strict_types=1);

namespace CampBuddy\Middleware;

use CampBuddy\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;

/**
 * Guards every /admin/* route except /admin/login. Also enforces idle and
 * absolute session timeouts.
 */
final class SessionAuthMiddleware
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function __invoke(ServerRequestInterface $request, Handler $handler): ResponseInterface
    {
        $now = time();
        $loggedIn = !empty($_SESSION['admin_id']);
        $idleExpired = $loggedIn && ($now - (int) ($_SESSION['last_activity'] ?? 0)) > $this->settings->sessionIdleTimeoutSeconds;
        $absoluteExpired = $loggedIn && ($now - (int) ($_SESSION['login_time'] ?? 0)) > $this->settings->sessionAbsoluteTimeoutSeconds;

        if ($loggedIn && ($idleExpired || $absoluteExpired)) {
            $_SESSION = [];
            session_destroy();
            $loggedIn = false;
        }

        if (!$loggedIn) {
            $response = new Response(302);
            return $response->withHeader('Location', $this->settings->adminUrl('/login'));
        }

        $_SESSION['last_activity'] = $now;

        return $handler->handle($request);
    }
}
