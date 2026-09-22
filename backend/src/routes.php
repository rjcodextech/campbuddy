<?php

declare(strict_types=1);

use CampBuddy\Actions\Admin\DashboardAction;
use CampBuddy\Actions\Admin\LoginAction;
use CampBuddy\Actions\Admin\LogoutAction;
use CampBuddy\Actions\Admin\OffersDeleteAction;
use CampBuddy\Actions\Admin\OffersListAction;
use CampBuddy\Actions\Admin\OffersSaveAction;
use CampBuddy\Actions\Admin\OverrideAction;
use CampBuddy\Actions\Admin\PurgeCacheAction;
use CampBuddy\Actions\Admin\RefreshAction;
use CampBuddy\Actions\Admin\SettingsAction;
use CampBuddy\Actions\Api\AgendaAction;
use CampBuddy\Actions\Api\EventAction;
use CampBuddy\Actions\Api\HealthAction;
use CampBuddy\Actions\Api\MediaAction;
use CampBuddy\Actions\Api\OffersAction;
use CampBuddy\Actions\Api\SponsorsAction;
use CampBuddy\Middleware\CsrfMiddleware;
use CampBuddy\Middleware\RateLimitMiddleware;
use CampBuddy\Middleware\SessionAuthMiddleware;
use CampBuddy\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app): void {
    $container = $app->getContainer();
    $settings = $container->get(Settings::class);
    $pdo = $container->get(PDO::class);

    $apiRateLimit = new RateLimitMiddleware($pdo, 'api', $settings->rateLimitApiPerMinute);
    $loginRateLimit = new RateLimitMiddleware($pdo, 'login', $settings->rateLimitLoginPerMinute);
    $csrf = new CsrfMiddleware();
    $sessionAuth = new SessionAuthMiddleware($settings);

    // Bare /backend and /backend/ — send visitors straight to the admin
    // panel (which itself redirects to /login if not authenticated).
    $redirectToAdmin = function (ServerRequestInterface $request, ResponseInterface $response) use ($settings): ResponseInterface {
        return $response->withStatus(302)->withHeader('Location', $settings->adminUrl());
    };
    $app->get('', $redirectToAdmin);
    $app->get('/', $redirectToAdmin);

    $app->group('/api/v1', function (Group $group): void {
        $group->get('/event', EventAction::class);
        $group->get('/media', MediaAction::class);
        $group->get('/sponsors', SponsorsAction::class);
        $group->get('/agenda', AgendaAction::class);
        $group->get('/offers', OffersAction::class);
        $group->get('/health', HealthAction::class);
    })->add($apiRateLimit);

    $app->group('/admin', function (Group $group) use ($csrf): void {
        $group->map(['GET', 'POST'], '/login', LoginAction::class)->add($csrf);
    })->add($loginRateLimit);

    $app->group('/admin', function (Group $group) use ($csrf): void {
        $group->get('', DashboardAction::class);
        $group->post('/logout', LogoutAction::class)->add($csrf);
        $group->post('/refresh', RefreshAction::class)->add($csrf);
        $group->post('/override', OverrideAction::class)->add($csrf);
        $group->post('/settings', SettingsAction::class)->add($csrf);
        $group->post('/purge-cache', PurgeCacheAction::class)->add($csrf);
        $group->get('/offers', OffersListAction::class);
        $group->post('/offers', OffersSaveAction::class)->add($csrf);
        $group->post('/offers/delete', OffersDeleteAction::class)->add($csrf);
    })->add($sessionAuth);
};
