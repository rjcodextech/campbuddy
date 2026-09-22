<?php

declare(strict_types=1);

use CampBuddy\Cache\CacheInterface;
use CampBuddy\Cache\DbCache;
use CampBuddy\Domain\Event\EventFetcher;
use CampBuddy\Domain\Media\MediaFetcher;
use CampBuddy\Repository\AdminUserRepository;
use CampBuddy\Repository\AppSettingsRepository;
use CampBuddy\Repository\EventRepository;
use CampBuddy\Repository\FetchLogRepository;
use CampBuddy\Repository\MediaRepository;
use CampBuddy\Repository\OfferRepository;
use CampBuddy\Settings;
use CampBuddy\Support\AssetVersionBumper;
use CampBuddy\Support\Database;
use CampBuddy\Support\UpstreamClient;
use CampBuddy\Support\View;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

return [
    Settings::class => static fn () => Settings::fromEnv(dirname(__DIR__)),

    PDO::class => static fn (Settings $settings) => Database::connect($settings),

    LoggerInterface::class => static function (Settings $settings) {
        $logger = new Logger('campbuddy');
        $path = $settings->basePath . '/' . ltrim($settings->logPath, '/');
        $logger->pushHandler(new StreamHandler($path, $settings->appDebug ? Level::Debug : Level::Info));
        return $logger;
    },

    UpstreamClient::class => static fn (LoggerInterface $logger) => new UpstreamClient($logger),

    CacheInterface::class => static fn (PDO $pdo) => new DbCache($pdo),

    EventFetcher::class => static fn (UpstreamClient $client, Settings $settings) => new EventFetcher($client, $settings),
    MediaFetcher::class => static fn (UpstreamClient $client, Settings $settings) => new MediaFetcher($client, $settings),

    FetchLogRepository::class => static fn (PDO $pdo) => new FetchLogRepository($pdo),
    AdminUserRepository::class => static fn (PDO $pdo) => new AdminUserRepository($pdo),
    AppSettingsRepository::class => static fn (PDO $pdo) => new AppSettingsRepository($pdo),
    OfferRepository::class => static fn (PDO $pdo) => new OfferRepository($pdo),

    EventRepository::class => static fn (
        CacheInterface $cache,
        EventFetcher $fetcher,
        FetchLogRepository $fetchLog,
        PDO $pdo,
        Settings $settings,
        AppSettingsRepository $appSettings,
    ) => new EventRepository($cache, $fetcher, $fetchLog, $pdo, $settings, $appSettings),

    MediaRepository::class => static fn (
        CacheInterface $cache,
        MediaFetcher $fetcher,
        FetchLogRepository $fetchLog,
        Settings $settings,
    ) => new MediaRepository($cache, $fetcher, $fetchLog, $settings),

    View::class => static fn (Settings $settings) => new View($settings->basePath . '/resources/views'),

    AssetVersionBumper::class => static fn (Settings $settings) => new AssetVersionBumper($settings->projectRoot()),
];
