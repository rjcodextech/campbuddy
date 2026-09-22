<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Admin;

use CampBuddy\Repository\OfferRepository;
use CampBuddy\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Create or update, decided by whether a valid "id" was submitted — one
 * form on the offers page serves both the "Add new" and "Edit" flows.
 */
final class OffersSaveAction
{
    private const MAX_TITLE_LENGTH = 120;
    private const MAX_DESCRIPTION_LENGTH = 255;
    private const MAX_URL_LENGTH = 500;

    public function __construct(
        private readonly OfferRepository $offers,
        private readonly Settings $settings,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();

        $title = trim(mb_substr((string) ($body['title'] ?? ''), 0, self::MAX_TITLE_LENGTH));
        $description = trim(mb_substr((string) ($body['description'] ?? ''), 0, self::MAX_DESCRIPTION_LENGTH));
        $url = trim(mb_substr((string) ($body['url'] ?? ''), 0, self::MAX_URL_LENGTH));
        $icon = trim(mb_substr((string) ($body['icon'] ?? '🏷'), 0, 10)) ?: '🏷';
        $sortOrder = (int) ($body['sort_order'] ?? 0);
        $isActive = !empty($body['is_active']);
        $id = (int) ($body['id'] ?? 0);

        if ($title === '' || $description === '' || !filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'http')) {
            $_SESSION['flash'] = 'Title, description and a valid http(s) URL are required.';
            return $response->withStatus(302)->withHeader('Location', $this->settings->adminUrl('/offers'));
        }

        if ($id > 0 && $this->offers->find($id) !== null) {
            $this->offers->update($id, $title, $description, $url, $icon, $sortOrder, $isActive);
            $_SESSION['flash'] = "Offer '{$title}' updated.";
        } else {
            $this->offers->create($title, $description, $url, $icon, $sortOrder);
            $_SESSION['flash'] = "Offer '{$title}' added.";
        }

        return $response->withStatus(302)->withHeader('Location', $this->settings->adminUrl('/offers'));
    }
}
