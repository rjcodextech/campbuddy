<?php

declare(strict_types=1);

namespace CampBuddy\Actions\Api;

use CampBuddy\Repository\OfferRepository;
use CampBuddy\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class OffersAction
{
    use JsonResponder;

    public function __construct(private readonly OfferRepository $offers)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $offers = array_map(
            static fn (array $o): array => [
                'id' => (int) $o['id'],
                'title' => $o['title'],
                'description' => $o['description'],
                'url' => $o['url'],
                'icon' => $o['icon'],
            ],
            $this->offers->listActive()
        );

        return $this->jsonCached($response, ['offers' => $offers]);
    }
}
