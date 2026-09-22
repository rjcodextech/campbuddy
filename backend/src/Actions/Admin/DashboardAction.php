<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Admin;

use CampBuddy\Middleware\CsrfMiddleware;
use CampBuddy\Repository\EventRepository;
use CampBuddy\Repository\FetchLogRepository;
use CampBuddy\Settings;
use CampBuddy\Support\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DashboardAction
{
    public function __construct(
        private readonly FetchLogRepository $fetchLog,
        private readonly EventRepository $events,
        private readonly View $view,
        private readonly Settings $settings,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $html = $this->view->render('admin/dashboard', [
            'csrfToken' => CsrfMiddleware::token(),
            'flash' => $flash,
            'eventLog' => $this->fetchLog->latest('event'),
            'mediaLog' => $this->fetchLog->latest('media'),
            'overrides' => $this->events->getOverrides(),
            'overridableFields' => EventRepository::OVERRIDABLE_FIELDS,
            'eventSlug' => $this->events->getEventSlug(),
            'logoutUrl' => $this->settings->adminUrl('/logout'),
            'refreshUrl' => $this->settings->adminUrl('/refresh'),
            'overrideUrl' => $this->settings->adminUrl('/override'),
            'settingsUrl' => $this->settings->adminUrl('/settings'),
            'purgeCacheUrl' => $this->settings->adminUrl('/purge-cache'),
            'offersUrl' => $this->settings->adminUrl('/offers'),
        ]);
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
