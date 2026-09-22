<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Admin;

use CampBuddy\Middleware\CsrfMiddleware;
use CampBuddy\Repository\AdminUserRepository;
use CampBuddy\Settings;
use CampBuddy\Support\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LoginAction
{
    public function __construct(
        private readonly AdminUserRepository $users,
        private readonly View $view,
        private readonly Settings $settings,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!empty($_SESSION['admin_id'])) {
            return $response->withStatus(302)->withHeader('Location', $this->settings->adminUrl());
        }

        if ($request->getMethod() === 'POST') {
            return $this->handleLogin($request, $response);
        }

        $html = $this->view->render('admin/login', [
            'csrfToken' => CsrfMiddleware::token(),
            'error' => null,
            'loginUrl' => $this->settings->adminUrl('/login'),
        ]);
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function handleLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        $user = $username !== '' ? $this->users->findByUsername($username) : null;

        // Always run password_verify, even against a dummy hash, so a
        // nonexistent username doesn't respond measurably faster than a
        // wrong password (timing side-channel for username enumeration).
        $hash = $user['password_hash'] ?? '$2y$10$invalidsaltinvalidsaltuinvalidsalt';
        $valid = $user !== null && !$this->users->isLocked($user) && password_verify($password, $hash);

        if (!$valid) {
            if ($user !== null) {
                $this->users->recordFailedLogin($user['id']);
            }
            $html = $this->view->render('admin/login', [
                'csrfToken' => CsrfMiddleware::token(),
                'error' => 'Incorrect username or password.',
                'loginUrl' => $this->settings->adminUrl('/login'),
            ]);
            $response->getBody()->write($html);
            return $response->withStatus(401)->withHeader('Content-Type', 'text/html; charset=utf-8');
        }

        $this->users->resetFailedLogins($user['id']);

        session_regenerate_id(true);
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        return $response->withStatus(302)->withHeader('Location', $this->settings->adminUrl());
    }
}
