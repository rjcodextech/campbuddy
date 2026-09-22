<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Admin;

use CampBuddy\Repository\EventRepository;
use CampBuddy\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class OverrideAction
{
    private const MAX_FIELD_LENGTH = 500;

    public function __construct(
        private readonly EventRepository $events,
        private readonly Settings $settings,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $submitted = is_array($body['fields'] ?? null) ? $body['fields'] : [];

        $overrides = [];
        foreach (EventRepository::OVERRIDABLE_FIELDS as $field => $label) {
            $value = trim((string) ($submitted[$field] ?? ''));
            if ($value !== '') {
                $overrides[$field] = mb_substr($value, 0, self::MAX_FIELD_LENGTH);
            }
        }

        $this->events->setOverrides($overrides, $_SESSION['admin_username'] ?? null);

        $_SESSION['flash'] = 'Overrides saved.';

        return $response->withStatus(302)->withHeader('Location', $this->settings->adminUrl());
    }
}
