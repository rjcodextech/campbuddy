<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Admin;

use CampBuddy\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LogoutAction
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $_SESSION = [];
        session_destroy();

        return $response->withStatus(302)->withHeader('Location', $this->settings->adminUrl('/login'));
    }
}
