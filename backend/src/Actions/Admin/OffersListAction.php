<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Admin;

use CampBuddy\Middleware\CsrfMiddleware;
use CampBuddy\Repository\OfferRepository;
use CampBuddy\Settings;
use CampBuddy\Support\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class OffersListAction
{
    public function __construct(
        private readonly OfferRepository $offers,
        private readonly View $view,
        private readonly Settings $settings,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $editId = $request->getQueryParams()['edit'] ?? null;
        $editing = $editId !== null ? $this->offers->find((int) $editId) : null;

        $html = $this->view->render('admin/offers', [
            'csrfToken' => CsrfMiddleware::token(),
            'flash' => $flash,
            'offers' => $this->offers->listAll(),
            'editing' => $editing,
            'adminUrl' => $this->settings->adminUrl(),
            'saveUrl' => $this->settings->adminUrl('/offers'),
            'deleteUrl' => $this->settings->adminUrl('/offers/delete'),
            'offersUrl' => $this->settings->adminUrl('/offers'),
        ]);
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
