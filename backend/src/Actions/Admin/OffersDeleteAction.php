<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Admin;

use CampBuddy\Repository\OfferRepository;
use CampBuddy\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class OffersDeleteAction
{
    public function __construct(
        private readonly OfferRepository $offers,
        private readonly Settings $settings,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $id = (int) ($body['id'] ?? 0);

        if ($id > 0) {
            $this->offers->delete($id);
            $_SESSION['flash'] = 'Offer deleted.';
        }

        return $response->withStatus(302)->withHeader('Location', $this->settings->adminUrl('/offers'));
    }
}
